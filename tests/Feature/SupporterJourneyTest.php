<?php

use App\Actions\Engagement\ApproveSupporterJourney;
use App\Actions\Engagement\DispatchSupporterJourney;
use App\Actions\Engagement\TransitionSupporterJourneyRecipient;
use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\StorePartyContact;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyContactType;
use App\Enums\SupporterJourneyEventType;
use App\Enums\SupporterJourneyRecipientStatus;
use App\Enums\SupporterJourneyStatus;
use App\Models\AudienceSegment;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\SupporterJourney;
use App\Models\SupporterJourneyEvent;
use App\Models\SupporterJourneyRecipient;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('j', 32));
    config([
        'classified_data.encryption.current_version' => 'journey-data-v1',
        'classified_data.encryption.keys' => ['journey-data-v1' => $key],
        'classified_data.contact_index.current_version' => 'journey-index-v1',
        'classified_data.contact_index.previous_version' => null,
        'classified_data.contact_index.keys' => ['journey-index-v1' => $key],
        'engagement.simulation_only' => true,
    ]);
});

it('freezes approval and rechecks safety before a local simulated dispatch', function () {
    [$organisation, $officer] = journeyOrganisation();

    [$journey, $eligible, $withdrawn] = app(OrganisationContext::class)->run($organisation, function () use ($officer, $organisation): array {
        $eligible = journeySupporter($organisation, $officer, 'Eligible Rowan');
        $withdrawn = journeySupporter($organisation, $officer, 'Withdrawn Avery');
        $segment = journeySegment('Consented donors');
        $journey = SupporterJourney::factory()->create([
            'audience_segment_id' => $segment->id,
            'name' => 'Donation thank-you and welcome',
        ]);
        app(ApproveSupporterJourney::class)->handle($journey, $officer);
        app(RecordPartyConsent::class)->handle($withdrawn, [
            'purpose' => ConsentPurpose::SupporterUpdates,
            'channel' => ConsentChannel::Email,
            'decision' => ConsentDecision::Withdrawn,
            'wording_version' => 'v1',
            'wording' => 'Stop simulated supporter updates.',
            'source' => 'preference-centre',
            'occurred_at' => now()->addSecond()->toAtomString(),
        ], $officer);
        app(DispatchSupporterJourney::class)->handle($journey->fresh());

        return [$journey->fresh(), $eligible, $withdrawn];
    });

    expect($journey->status)->toBe(SupporterJourneyStatus::Approved)
        ->and($journey->audience_snapshot)->toHaveCount(2)
        ->and($journey->approval_hash)->toHaveLength(64);
    app(OrganisationContext::class)->run($organisation, function () use ($eligible, $journey, $withdrawn): void {
        expect(SupporterJourneyRecipient::query()->whereBelongsTo($journey, 'journey')->whereBelongsTo($eligible)->sole()->status)->toBe(SupporterJourneyRecipientStatus::Queued)
            ->and(SupporterJourneyRecipient::query()->whereBelongsTo($journey, 'journey')->whereBelongsTo($withdrawn)->sole()->status)->toBe(SupporterJourneyRecipientStatus::Cancelled);
        expect(fn () => $journey->update(['body' => 'Changed after approval']))->toThrow(LogicException::class);
    });
});

it('runs deterministic idempotent outcomes and suppresses future supporter email on unsubscribe', function () {
    [$organisation, $officer] = journeyOrganisation();

    app(OrganisationContext::class)->run($organisation, function () use ($officer, $organisation): void {
        $party = journeySupporter($organisation, $officer, 'Journey Morgan');
        app(StorePartyContact::class)->handle($party, PartyContactType::Telephone, '+27820000000');
        app(RecordPartyConsent::class)->handle($party, [
            'purpose' => ConsentPurpose::SupporterUpdates,
            'channel' => ConsentChannel::Sms,
            'decision' => ConsentDecision::Granted,
            'wording_version' => 'sms-v1',
            'wording' => 'I agree to simulated supporter SMS updates.',
            'source' => 'local-fixture',
            'occurred_at' => now()->subMinute()->toAtomString(),
        ], $officer);
        $journey = SupporterJourney::factory()->create(['audience_segment_id' => journeySegment(channel: ConsentChannel::Sms)->id]);
        app(ApproveSupporterJourney::class)->handle($journey, $officer);
        $recipient = app(DispatchSupporterJourney::class)->handle($journey->fresh())->sole();
        $transition = app(TransitionSupporterJourneyRecipient::class);
        $deliveredKey = Str::uuid()->toString();
        $transition->handle($recipient, SupporterJourneyEventType::Delivered, $deliveredKey, $officer);
        $transition->handle($recipient->fresh(), SupporterJourneyEventType::Delivered, $deliveredKey, $officer);
        $transition->handle($recipient->fresh(), SupporterJourneyEventType::MeaningfulAction, Str::uuid()->toString(), $officer);
        $unsubscribeKey = Str::uuid()->toString();
        $transition->handle($recipient->fresh(), SupporterJourneyEventType::Unsubscribed, $unsubscribeKey, $officer);
        $transition->handle($recipient->fresh(), SupporterJourneyEventType::Unsubscribed, $unsubscribeKey, $officer);

        expect($recipient->fresh()->status)->toBe(SupporterJourneyRecipientStatus::Unsubscribed)
            ->and(SupporterJourneyEvent::query()->where('idempotency_key', $deliveredKey)->count())->toBe(1)
            ->and(SupporterJourneyEvent::query()->where('idempotency_key', $unsubscribeKey)->count())->toBe(1)
            ->and($party->consents()->where('channel', ConsentChannel::Sms)->latest('occurred_at')->firstOrFail()->decision)->toBe(ConsentDecision::Suppressed)
            ->and($party->consents()->where('channel', ConsentChannel::Email)->latest('occurred_at')->firstOrFail()->decision)->toBe(ConsentDecision::Granted)
            ->and($party->timelineEvents()->where('summary', 'like', 'Local supporter journey%')->count())->toBe(4);
    });
});

it('supports bounce and retry while enforcing the frequency cap', function () {
    [$organisation, $officer] = journeyOrganisation();

    app(OrganisationContext::class)->run($organisation, function () use ($officer, $organisation): void {
        $party = journeySupporter($organisation, $officer, 'Retry Taylor');
        $segment = journeySegment();
        $first = SupporterJourney::factory()->create(['audience_segment_id' => $segment->id, 'name' => 'First welcome']);
        app(ApproveSupporterJourney::class)->handle($first, $officer);
        $recipient = app(DispatchSupporterJourney::class)->handle($first->fresh())->sole();
        app(TransitionSupporterJourneyRecipient::class)->handle($recipient, SupporterJourneyEventType::Bounced, Str::uuid()->toString(), $officer);
        app(TransitionSupporterJourneyRecipient::class)->handle($recipient->fresh(), SupporterJourneyEventType::Retried, Str::uuid()->toString(), $officer);
        app(TransitionSupporterJourneyRecipient::class)->handle($recipient->fresh(), SupporterJourneyEventType::Delivered, Str::uuid()->toString(), $officer);

        $second = SupporterJourney::factory()->create(['audience_segment_id' => $segment->id, 'name' => 'Second welcome']);
        app(ApproveSupporterJourney::class)->handle($second, $officer);
        $capped = app(DispatchSupporterJourney::class)->handle($second->fresh())->sole();

        expect($recipient->fresh()->attempt_count)->toBe(1)
            ->and($capped->status)->toBe(SupporterJourneyRecipientStatus::Cancelled)
            ->and($party->timelineEvents()->get()->flatMap->metadata->join(' '))->not->toContain('email');
    });
});

it('refuses dispatch outside local or testing and restricts the interface to engagement officers', function () {
    [$organisation, $officer] = journeyOrganisation();
    $caseWorker = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $caseWorker->id, 'role' => OrganisationRole::CaseWorker]);
    $journey = app(OrganisationContext::class)->run($organisation, function () use ($officer, $organisation): SupporterJourney {
        journeySupporter($organisation, $officer, 'Safe Casey');
        $journey = SupporterJourney::factory()->create(['audience_segment_id' => journeySegment()->id]);
        app(ApproveSupporterJourney::class)->handle($journey, $officer);

        return $journey->fresh();
    });

    $this->actingAs($officer)->get(route('supporter-journeys.show', [$organisation, $journey]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('supporter-journeys/show')
            ->where('simulationOnly', true)
            ->missing('journey.audienceSnapshot.0.email')
            ->missing('journey.audienceSnapshot.0.serviceCases'));
    $this->actingAs($caseWorker)->get(route('supporter-journeys.index', $organisation))->assertForbidden();
    $this->actingAs($officer)->post(route('supporter-journeys.store', $organisation), [
        'audience_segment_id' => $journey->audience_segment_id,
        'name' => 'Unsafe template',
        'subject' => 'Supporter update',
        'body' => 'Case details: {{ service_case }}',
    ])->assertSessionHasErrors('body');

    app()->detectEnvironment(fn (): string => 'production');
    expect(fn () => app(OrganisationContext::class)->run($organisation, fn () => app(DispatchSupporterJourney::class)->handle($journey)))
        ->toThrow(LogicException::class, 'restricted to local simulation');
});

/** @return array{Organisation, User} */
function journeyOrganisation(): array
{
    $organisation = Organisation::factory()->active()->create();
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::EngagementOfficer]);

    return [$organisation, $officer];
}

function journeySupporter(Organisation $organisation, User $officer, string $name): Party
{
    $party = Party::factory()->for($organisation)->create(['display_name' => $name]);
    $party->businessRoles()->create(['organisation_id' => $organisation->id, 'role' => PartyBusinessRole::Donor]);
    app(StorePartyContact::class)->handle($party, PartyContactType::Email, Str::slug($name).'@example.test');
    app(RecordPartyConsent::class)->handle($party, [
        'purpose' => ConsentPurpose::SupporterUpdates,
        'channel' => ConsentChannel::Email,
        'decision' => ConsentDecision::Granted,
        'wording_version' => 'v1',
        'wording' => 'I agree to simulated supporter updates.',
        'source' => 'local-fixture',
        'occurred_at' => now()->subMinute()->toAtomString(),
    ], $officer);

    return $party;
}

function journeySegment(?string $name = null, ConsentChannel $channel = ConsentChannel::Email): AudienceSegment
{
    $factory = AudienceSegment::factory();

    return $factory->create([
        ...($name === null ? [] : ['name' => $name]),
        'criteria' => [
            ...$factory->raw()['criteria'],
            'channel' => $channel->value,
            'donation_activity' => false,
            'activity_type' => 'any',
            'minimum_frequency' => 0,
        ],
    ]);
}
