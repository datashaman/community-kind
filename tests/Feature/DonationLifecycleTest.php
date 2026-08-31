<?php

use App\Actions\Donations\CreateDonation;
use App\Actions\Donations\CreateDonationPayment;
use App\Actions\Donations\CreateRecurringMandate;
use App\Actions\Donations\RefundDonationPayment;
use App\Actions\Donations\TransitionDonationPayment;
use App\Actions\Donations\TransitionRecurringMandate;
use App\Donations\DonationPaymentProvider;
use App\Enums\DonationFrequency;
use App\Enums\DonationPaymentStatus;
use App\Enums\DonationSimulationScenario;
use App\Enums\OrganisationRole;
use App\Enums\RecurringMandateStatus;
use App\Models\Donation;
use App\Models\DonationFund;
use App\Models\DonationPaymentEvent;
use App\Models\DonationReceipt;
use App\Models\DonationRefund;
use App\Models\FundraisingCampaign;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\Models\RecurringMandateEvent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{Organisation, Party, DonationFund, FundraisingCampaign} */
function donationFixture(): array
{
    $organisation = Organisation::factory()->active()->create(['name' => 'HarbourKind', 'slug' => 'harbourkind']);

    return app(OrganisationContext::class)->run($organisation, fn (): array => [
        $organisation,
        Party::factory()->for($organisation)->create(['display_name' => 'Synthetic Donor Example']),
        DonationFund::factory()->for($organisation)->create(['name' => 'Demo Relief Fund', 'slug' => 'demo-relief']),
        FundraisingCampaign::factory()->for($organisation)->create(['name' => 'Demo Winter Appeal', 'slug' => 'demo-winter']),
    ]);
}

it('accepts only fixed public simulation choices and idempotently issues a marked demo receipt', function () {
    [$organisation, , $fund, $campaign] = donationFixture();
    $url = 'https://harbourkind.community-kind.test/donate';

    $this->get($url)
        ->assertOk()
        ->assertSee('Demo only—no money will move')
        ->assertDontSee('name="card_number"', false)
        ->assertDontSee('name="email"', false);

    $prohibited = ['amount_minor' => 5000, 'frequency' => 'one_off', 'fund_id' => $fund->id, 'campaign_id' => $campaign->id, 'idempotency_key' => Str::uuid()->toString(), 'card_number' => '4111111111111111'];
    $this->from($url)->post($url, $prohibited)->assertRedirect($url)->assertSessionHasErrors('card_number');
    expect(Donation::withoutGlobalScopes()->count())->toBe(0);

    $idempotencyKey = Str::uuid()->toString();
    $payload = ['amount_minor' => 5000, 'frequency' => 'monthly', 'fund_id' => $fund->id, 'campaign_id' => $campaign->id, 'idempotency_key' => $idempotencyKey];
    $this->post($url, $payload)
        ->assertOk()
        ->assertSee('Simulation complete')
        ->assertSee('Demo—Not a tax receipt')
        ->assertSee('No money moved')
        ->assertDontSee('4111111111111111');
    $this->post($url, $payload)->assertOk()->assertSee('Demo—Not a tax receipt');
    $this->post($url, [...$payload, 'amount_minor' => 10000])->assertConflict();

    app(OrganisationContext::class)->run($organisation, function (): void {
        $donation = Donation::query()->sole();
        expect($donation->is_simulated)->toBeTrue()
            ->and($donation->party->display_name)->toStartWith('Simulated Supporter ')
            ->and($donation->payments()->count())->toBe(1)
            ->and($donation->payments()->sole()->status)->toBe(DonationPaymentStatus::Succeeded)
            ->and($donation->mandate?->status)->toBe(RecurringMandateStatus::Active)
            ->and($donation->receipts()->sole()->marker)->toBe('Demo—Not a tax receipt')
            ->and(PartyContactPoint::query()->count())->toBe(0);
    });
});

it('reconciles retries duplicates out-of-order events refunds receipts and immutable money', function () {
    [$organisation, $party, $fund, $campaign] = donationFixture();

    app(OrganisationContext::class)->run($organisation, function () use ($campaign, $fund, $party): void {
        $donation = app(CreateDonation::class)->handle($party, $fund, $campaign, DonationFrequency::OneOff, 5000, 'ZAR', 'fixture', Str::uuid()->toString());
        $provider = app(DonationPaymentProvider::class);
        $declined = $provider->process($donation, DonationSimulationScenario::Decline, Str::uuid()->toString());
        expect($declined->status)->toBe(DonationPaymentStatus::Failed)
            ->and($declined->receipt)->toBeNull();

        $retryKey = Str::uuid()->toString();
        $succeeded = $provider->process($donation, DonationSimulationScenario::TimeoutThenSuccess, $retryKey);
        $provider->process($donation, DonationSimulationScenario::TimeoutThenSuccess, $retryKey);
        expect($succeeded->status)->toBe(DonationPaymentStatus::Succeeded)
            ->and($succeeded->receipt?->marker)->toBe('Demo—Not a tax receipt')
            ->and($donation->payments()->count())->toBe(3)
            ->and(DonationReceipt::query()->count())->toBe(1);

        $outOfOrder = app(CreateDonationPayment::class)->handle($donation, Str::uuid()->toString());
        $eventCount = DonationPaymentEvent::query()->count();
        expect(fn () => app(TransitionDonationPayment::class)->handle($outOfOrder, DonationPaymentStatus::Succeeded, Str::uuid()->toString(), now()))
            ->toThrow(LogicException::class, 'Cannot transition');
        expect($outOfOrder->refresh()->status)->toBe(DonationPaymentStatus::Created)
            ->and(DonationPaymentEvent::query()->count())->toBe($eventCount);

        $pendingKey = Str::uuid()->toString();
        $pending = app(TransitionDonationPayment::class)->handle($outOfOrder, DonationPaymentStatus::Pending, $pendingKey, now());
        app(TransitionDonationPayment::class)->handle($pending, DonationPaymentStatus::Pending, $pendingKey, now());
        expect($pending->events()->count())->toBe(1)
            ->and(fn () => $pending->update(['amount_minor' => 6000]))->toThrow(LogicException::class, 'money fields are immutable')
            ->and(fn () => $donation->update(['amount_minor' => 6000]))->toThrow(LogicException::class, 'money fields are immutable');

        $partialKey = Str::uuid()->toString();
        $partial = app(RefundDonationPayment::class)->handle($succeeded, 2000, $partialKey, now());
        $duplicate = app(RefundDonationPayment::class)->handle($succeeded->refresh(), 2000, $partialKey, now());
        expect($duplicate->is($partial))->toBeTrue()
            ->and($succeeded->refresh()->status)->toBe(DonationPaymentStatus::PartiallyRefunded)
            ->and(fn () => app(RefundDonationPayment::class)->handle($succeeded, 3001, Str::uuid()->toString(), now()))->toThrow(LogicException::class, 'cannot exceed');
        app(RefundDonationPayment::class)->handle($succeeded->refresh(), 3000, Str::uuid()->toString(), now());
        expect($succeeded->refresh()->status)->toBe(DonationPaymentStatus::Refunded)
            ->and(DonationRefund::query()->count())->toBe(2)
            ->and(DonationReceipt::query()->count())->toBe(1);
    });
});

it('requires new payment evidence for recurring failure recovery and prevents attempts after cancellation', function () {
    [$organisation, $party, $fund, $campaign] = donationFixture();

    app(OrganisationContext::class)->run($organisation, function () use ($campaign, $fund, $party): void {
        $donation = app(CreateDonation::class)->handle($party, $fund, $campaign, DonationFrequency::Monthly, 2500, 'ZAR', 'fixture', Str::uuid()->toString());
        $mandate = app(CreateRecurringMandate::class)->handle($donation);
        $provider = app(DonationPaymentProvider::class);
        $firstSuccess = $provider->process($donation->refresh(), DonationSimulationScenario::Success, Str::uuid()->toString());
        expect($mandate->refresh()->status)->toBe(RecurringMandateStatus::Active);

        $failed = $provider->process($donation->refresh(), DonationSimulationScenario::RecurringFailure, Str::uuid()->toString());
        expect($failed->status)->toBe(DonationPaymentStatus::Failed)
            ->and($mandate->refresh()->status)->toBe(RecurringMandateStatus::PaymentFailed)
            ->and(fn () => app(TransitionRecurringMandate::class)->handle($mandate, RecurringMandateStatus::Active, Str::uuid()->toString(), now(), $firstSuccess))
            ->toThrow(LogicException::class, 'after the failure');

        $provider->process($donation->refresh(), DonationSimulationScenario::Success, Str::uuid()->toString());
        expect($mandate->refresh()->status)->toBe(RecurringMandateStatus::Active);

        $cancelKey = Str::uuid()->toString();
        app(TransitionRecurringMandate::class)->handle($mandate, RecurringMandateStatus::Cancelled, $cancelKey, now());
        app(TransitionRecurringMandate::class)->handle($mandate->refresh(), RecurringMandateStatus::Cancelled, $cancelKey, now());
        expect($mandate->refresh()->status)->toBe(RecurringMandateStatus::Cancelled)
            ->and(RecurringMandateEvent::query()->where('to_status', RecurringMandateStatus::Cancelled)->count())->toBe(1)
            ->and(fn () => app(CreateDonationPayment::class)->handle($donation, Str::uuid()->toString(), $mandate))
            ->toThrow(LogicException::class, 'cannot create');
    });
});

it('allows only engagement officers to follow supporter-safe simulated donation records', function () {
    [$organisation, $party, $fund, $campaign] = donationFixture();
    $engagement = User::factory()->create();
    $caseWorker = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $engagement->id, 'role' => OrganisationRole::EngagementOfficer]);
    $organisation->memberships()->create(['user_id' => $caseWorker->id, 'role' => OrganisationRole::CaseWorker]);
    $donation = app(OrganisationContext::class)->run($organisation, function () use ($campaign, $fund, $party): Donation {
        $donation = app(CreateDonation::class)->handle($party, $fund, $campaign, DonationFrequency::OneOff, 5000, 'ZAR', 'fixture', Str::uuid()->toString());
        app(DonationPaymentProvider::class)->process($donation, DonationSimulationScenario::Success, Str::uuid()->toString());

        return $donation;
    });

    $this->actingAs($engagement)
        ->get(route('donations.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('donations/index')
            ->has('donations.data', 1));
    $showUrl = '/'.$organisation->slug.'/donations/'.$donation->getKey();
    expect($donation->getKey())->toBeString()->not->toBeEmpty()
        ->and($showUrl)->toEndWith((string) $donation->getKey());
    $this->actingAs($engagement)
        ->get($showUrl)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('donations/show')
            ->where('donation.payments.0.receipt.marker', 'Demo—Not a tax receipt'));
    $this->actingAs($caseWorker)->get(route('donations.index', $organisation))->assertForbidden();
    $this->actingAs($caseWorker)->get($showUrl)->assertForbidden();
});
