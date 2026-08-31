<?php

use App\Actions\Donations\CreateDonation;
use App\Actions\Engagement\EvaluateAudienceSegment;
use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\StorePartyContact;
use App\Donations\DonationPaymentProvider;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\DonationFrequency;
use App\Enums\DonationSimulationScenario;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyContactType;
use App\Enums\TenantAuditEventType;
use App\Models\AudienceSegment;
use App\Models\DonationFund;
use App\Models\FundraisingCampaign;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyInterest;
use App\Models\PartySafeContactInstruction;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('s', 32));
    config([
        'classified_data.encryption.current_version' => 'segment-data-v1',
        'classified_data.encryption.keys' => ['segment-data-v1' => $key],
        'classified_data.contact_index.current_version' => 'segment-index-v1',
        'classified_data.contact_index.previous_version' => null,
        'classified_data.contact_index.keys' => ['segment-index-v1' => $key],
    ]);
});

it('builds a reproducible supporter-safe audience and excludes every safety failure', function () {
    $organisation = Organisation::factory()->active()->create(['slug' => 'harbourkind']);
    $otherOrganisation = Organisation::factory()->active()->create();
    $engagement = User::factory()->create();
    $caseWorker = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $engagement->id, 'role' => OrganisationRole::EngagementOfficer]);
    $organisation->memberships()->create(['user_id' => $caseWorker->id, 'role' => OrganisationRole::CaseWorker]);

    [$segment, $eligible] = app(OrganisationContext::class)->run($organisation, function () use ($engagement, $organisation): array {
        $fund = DonationFund::factory()->for($organisation)->create();
        $campaign = FundraisingCampaign::factory()->for($organisation)->create();
        $makeSupporter = function (string $name, ConsentDecision $decision, bool $unsafe = false, string $source = 'showcase_fixture') use ($campaign, $engagement, $fund, $organisation): Party {
            $party = Party::factory()->for($organisation)->create(['display_name' => $name]);
            $party->businessRoles()->create(['organisation_id' => $organisation->id, 'role' => PartyBusinessRole::Donor]);
            app(StorePartyContact::class)->handle($party, PartyContactType::Email, Str::slug($name).'-safe@example.test');
            PartyAddress::factory()->for($party)->create(['service_area' => 'Harbour Ward']);
            PartyInterest::factory()->for($party)->create(['slug' => 'winter-relief', 'label' => 'Winter relief']);
            $donation = app(CreateDonation::class)->handle($party, $fund, $campaign, DonationFrequency::OneOff, 5000, 'ZAR', $source, Str::uuid()->toString());
            app(DonationPaymentProvider::class)->process($donation, DonationSimulationScenario::Success, Str::uuid()->toString());
            app(RecordPartyConsent::class)->handle($party, [
                'purpose' => ConsentPurpose::SupporterUpdates,
                'channel' => ConsentChannel::Email,
                'decision' => ConsentDecision::Granted,
                'wording_version' => 'v1',
                'wording' => 'I agree to simulated supporter email updates.',
                'source' => 'winter-warmth-demo',
                'occurred_at' => now()->subMinute()->toAtomString(),
            ], $engagement);

            if ($decision !== ConsentDecision::Granted) {
                app(RecordPartyConsent::class)->handle($party, [
                    'purpose' => ConsentPurpose::SupporterUpdates,
                    'channel' => ConsentChannel::Email,
                    'decision' => $decision,
                    'wording_version' => 'v1',
                    'wording' => 'Do not send simulated supporter email updates.',
                    'source' => 'preference-centre-demo',
                    'occurred_at' => now()->toAtomString(),
                ], $engagement);
            }

            if ($unsafe) {
                PartySafeContactInstruction::factory()->for($party)->create(['effective_at' => now()->subMinute()]);
            }

            return $party;
        };

        $eligible = $makeSupporter('Eligible Rowan', ConsentDecision::Granted);
        $eligible->businessRoles()->create(['organisation_id' => $organisation->id, 'role' => PartyBusinessRole::Client]);
        $makeSupporter('Withdrawn Supporter', ConsentDecision::Withdrawn);
        $makeSupporter('Suppressed Supporter', ConsentDecision::Suppressed);
        $makeSupporter('Unsafe Supporter', ConsentDecision::Granted, true);
        $makeSupporter('Wrong Source Supporter', ConsentDecision::Granted, false, 'another_source');
        $segment = AudienceSegment::factory()->create([
            'name' => 'Winter Ward donors',
            'criteria' => [
                'purpose' => ConsentPurpose::SupporterUpdates->value,
                'channel' => ConsentChannel::Email->value,
                'role' => PartyBusinessRole::Donor->value,
                'service_area' => 'Harbour Ward',
                'interest' => 'winter-relief',
                'donation_activity' => true,
                'campaign_source' => 'showcase_fixture',
            ],
            'created_by_user_id' => $engagement->id,
        ]);

        return [$segment, $eligible];
    });

    app(OrganisationContext::class)->run($otherOrganisation, function () use ($otherOrganisation): void {
        $party = Party::factory()->for($otherOrganisation)->create(['display_name' => 'Other tenant supporter']);
        $party->businessRoles()->create(['organisation_id' => $otherOrganisation->id, 'role' => PartyBusinessRole::Donor]);
    });

    $audience = app(OrganisationContext::class)->run($organisation, fn () => app(EvaluateAudienceSegment::class)->handle($segment));
    $sourceOnlyAudience = app(OrganisationContext::class)->run($organisation, function () use ($segment): Collection {
        $sourceOnly = AudienceSegment::factory()->create([
            'name' => 'Campaign-source-only donors',
            'criteria' => [...$segment->criteria, 'donation_activity' => false],
        ]);

        return app(EvaluateAudienceSegment::class)->handle($sourceOnly);
    });
    expect($audience)->toHaveCount(1)
        ->and($sourceOnlyAudience)->toHaveCount(1)
        ->and($audience->sole()['uuid'])->toBe($eligible->uuid)
        ->and($audience->sole())->not->toHaveKeys(['serviceCases', 'programs', 'clientRole']);

    app(OrganisationContext::class)->run($organisation, fn () => app(RecordPartyConsent::class)->handle($eligible, [
        'purpose' => ConsentPurpose::Service,
        'decision' => ConsentDecision::Granted,
        'wording_version' => 'service-v1',
        'wording' => 'Synthetic service consent that engagement staff must not see.',
        'source' => 'service-intake',
        'occurred_at' => now()->toAtomString(),
    ], $engagement));
    $this->actingAs($engagement)
        ->get(route('parties.show', [$organisation, $eligible]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('party.consents', 1)
            ->where('party.consents.0.purpose', ConsentPurpose::SupporterUpdates->value));
    $this->actingAs($engagement)->post(route('parties.consents.store', [$organisation, $eligible]), [
        'purpose' => ConsentPurpose::Service->value,
        'channel' => ConsentChannel::NotApplicable->value,
        'decision' => ConsentDecision::Granted->value,
        'wording_version' => 'service-v2',
        'wording' => 'An engagement officer must not record this.',
        'source' => 'staff-screen',
        'occurred_at' => now()->toAtomString(),
    ])->assertForbidden();

    $this->actingAs($engagement)
        ->get(route('audience-segments.show', [$organisation, $segment]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('audience-segments/show')
            ->where('eligibleCount', 1)
            ->where('audience.0.uuid', $eligible->uuid));
    $this->actingAs($engagement)->post(route('audience-segments.store', $organisation), [
        'name' => 'All consented donors',
        'purpose' => ConsentPurpose::SupporterUpdates->value,
        'channel' => ConsentChannel::Email->value,
        'role' => PartyBusinessRole::Donor->value,
        'service_area' => null,
        'interest' => null,
        'donation_activity' => false,
        'campaign_source' => null,
    ])->assertRedirect();
    expect(app(OrganisationContext::class)->run($organisation, fn (): bool => AudienceSegment::query()->where('name', 'All consented donors')->exists()))->toBeTrue()
        ->and(app(OrganisationContext::class)->run($organisation, fn (): bool => TenantAuditEvent::query()->where('type', TenantAuditEventType::AudienceSegmentCreated)->exists()))->toBeTrue();
    $this->actingAs($caseWorker)->get(route('audience-segments.index', $organisation))->assertForbidden();
    $this->actingAs($caseWorker)->get(route('audience-segments.show', [$organisation, $segment]))->assertForbidden();
});
