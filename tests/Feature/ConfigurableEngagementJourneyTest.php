<?php

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Actions\Engagement\ApproveSupporterJourney;
use App\Actions\Engagement\DispatchSupporterJourney;
use App\Actions\Engagement\EvaluateAudienceSegment;
use App\Actions\Engagement\TransitionSupporterJourney;
use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\StorePartyContact;
use App\Actions\Reporting\BuildImpactReportPack;
use App\Actions\Reporting\PublishImpactSnapshot;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\DonationPaymentStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyContactType;
use App\Enums\SupporterJourneyRecipientStatus;
use App\Enums\SupporterJourneyStatus;
use App\Enums\TenantAuditEventType;
use App\Http\Requests\Public\StoreVolunteerApplicationRequest;
use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\Models\EventRegistration;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\Party;
use App\Models\PublishedImpactSnapshot;
use App\Models\SupporterJourney;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\Models\VolunteerHourEntry;
use App\OrganisationContext;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('c', 32));
    config([
        'classified_data.encryption.current_version' => 'configurable-v1',
        'classified_data.encryption.keys' => ['configurable-v1' => $key],
        'classified_data.contact_index.current_version' => 'configurable-index-v1',
        'classified_data.contact_index.previous_version' => null,
        'classified_data.contact_index.keys' => ['configurable-index-v1' => $key],
        'engagement.simulation_only' => true,
    ]);
});

it('versions, previews, audits, and protects organisation configuration', function () {
    [$organisation, $administrator] = configurableOrganisation(OrganisationRole::OrganisationAdministrator);
    $officer = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $officer->id, 'role' => OrganisationRole::EngagementOfficer]);

    app(OrganisationContext::class)->run($organisation, function () use ($administrator, $organisation): void {
        $create = app(CreateOrganisationConfiguration::class);
        expect(fn () => $create->handle($organisation, OrganisationConfigurationArea::PublicForm, 'volunteer', [
            'form' => 'volunteer_registration',
            'required_fields' => ['name'],
        ], $administrator))->toThrow(ValidationException::class);

        $first = $create->handle($organisation, OrganisationConfigurationArea::PublicForm, 'volunteer_registration', [
            'form' => 'volunteer_registration',
            'required_fields' => ['name', 'email'],
        ], $administrator);
        app(ActivateOrganisationConfiguration::class)->handle($first, $administrator);
        $second = $create->handle($organisation, OrganisationConfigurationArea::PublicForm, 'volunteer_registration', [
            'form' => 'volunteer_registration',
            'required_fields' => ['name', 'email', 'interests'],
        ], $administrator);
        app(ActivateOrganisationConfiguration::class)->handle($second, $administrator);

        expect($first->refresh()->status)->toBe(OrganisationConfigurationStatus::Superseded)
            ->and($second->refresh()->status)->toBe(OrganisationConfigurationStatus::Active)
            ->and($second->version)->toBe(2)
            ->and($second->supersedes_id)->toBe($first->id)
            ->and(fn () => $second->update(['definition' => ['changed' => true]]))->toThrow(LogicException::class)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::OrganisationConfigurationCreated)->count())->toBe(2)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::OrganisationConfigurationActivated)->count())->toBe(2);
        $publicFormValidator = Validator::make([
            'name' => 'Taylor',
            'email' => 'taylor@example.test',
            'availability' => ['Saturday'],
            'consent_email' => true,
        ], (new StoreVolunteerApplicationRequest)->rules());
        expect($publicFormValidator->errors()->has('interests'))->toBeTrue();
    });

    $this->actingAs($administrator)->get(route('organisation-configurations.index', $organisation))
        ->assertOk()
        ->assertSee('interests');
    $this->actingAs($administrator)->post(route('organisation-configurations.store', $organisation), [
        'area' => OrganisationConfigurationArea::PublicForm->value,
        'configuration_key' => 'volunteer_registration',
        'definition_json' => json_encode(['form' => 'volunteer_registration', 'required_fields' => ['name']], JSON_THROW_ON_ERROR),
    ])->assertSessionHasErrors('definition_json');
    $this->actingAs($officer)->get(route('organisation-configurations.index', $organisation))->assertForbidden();
});

it('evaluates declared donation, event, and volunteer recency-frequency-value semantics', function () {
    [$organisation, $officer] = configurableOrganisation(OrganisationRole::EngagementOfficer);

    app(OrganisationContext::class)->run($organisation, function () use ($officer, $organisation): void {
        $donor = configurableSupporter($organisation, $officer, 'Recent donor', PartyBusinessRole::Donor);
        $donation = Donation::factory()->create(['party_id' => $donor->id, 'amount_minor' => 6000]);
        DonationPayment::factory()->for($donation)->create([
            'attempt_number' => 1,
            'amount_minor' => 10000,
            'status' => DonationPaymentStatus::Succeeded,
            'settled_at' => now()->subDays(90),
        ]);
        DonationPayment::factory()->for($donation)->create([
            'attempt_number' => 2,
            'amount_minor' => 6000,
            'status' => DonationPaymentStatus::Succeeded,
            'settled_at' => now()->subDay(),
        ]);

        $attendee = configurableSupporter($organisation, $officer, 'Event attendee', PartyBusinessRole::EventAttendee);
        EventRegistration::factory()->create([
            'party_id' => $attendee->id,
            'status' => EventRegistrationStatus::Attended,
            'attended_at' => now()->subDays(2),
        ]);

        $volunteer = configurableSupporter($organisation, $officer, 'Active volunteer', PartyBusinessRole::Volunteer);
        VolunteerHourEntry::factory()->create([
            'party_id' => $volunteer->id,
            'minutes' => 180,
            'occurred_at' => now()->subDays(3),
        ]);

        $donationAudience = app(EvaluateAudienceSegment::class)->handle(configurableSegment(PartyBusinessRole::Donor, 'donation', 30, 1, 5000));
        $eventAudience = app(EvaluateAudienceSegment::class)->handle(configurableSegment(PartyBusinessRole::EventAttendee, 'event', 30, 1, 1));
        $volunteerAudience = app(EvaluateAudienceSegment::class)->handle(configurableSegment(PartyBusinessRole::Volunteer, 'volunteer', 30, 1, 120));

        expect($donationAudience->sole()['activityFrequency'])->toBe(1)
            ->and($donationAudience->sole()['activityValue'])->toBe(6000)
            ->and($eventAudience->sole()['uuid'])->toBe($attendee->uuid)
            ->and($eventAudience->sole()['activityValue'])->toBe(1)
            ->and($volunteerAudience->sole()['uuid'])->toBe($volunteer->uuid)
            ->and($volunteerAudience->sole()['activityValue'])->toBe(180);
    });
});

it('uses SMS templates, scheduling, pausing, experiments, and dispatch-time consent checks', function () {
    [$organisation, $officer] = configurableOrganisation(OrganisationRole::EngagementOfficer);

    $journey = app(OrganisationContext::class)->run($organisation, function () use ($officer, $organisation): SupporterJourney {
        $party = configurableSupporter($organisation, $officer, 'SMS supporter', PartyBusinessRole::Donor, ConsentChannel::Sms);
        $segment = configurableSegment(PartyBusinessRole::Donor, 'any', null, 0, null, ConsentChannel::Sms);
        $template = app(CreateOrganisationConfiguration::class)->handle($organisation, OrganisationConfigurationArea::MessageTemplate, 're-engagement-sms', [
            'channel' => 'sms',
            'body' => 'Hello {{ supporter_name }}, we miss you.',
            'journey_kind' => 're_engagement',
        ], $officer);
        app(ActivateOrganisationConfiguration::class)->handle($template, $officer);

        $this->actingAs($officer)->post(route('supporter-journeys.store', $organisation), [
            'audience_segment_id' => $segment->id,
            'message_template_key' => 're-engagement-sms',
            'name' => 'SMS re-engagement experiment',
            'experiment_enabled' => true,
            'variant_subject' => '',
            'variant_body' => 'Hi {{ supporter_name }}, can we reconnect?',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $journey = SupporterJourney::query()->where('name', 'SMS re-engagement experiment')->sole();
        expect($journey->channel)->toBe('sms')->and($journey->subject)->toBe('');

        app(ApproveSupporterJourney::class)->handle($journey, $officer);
        app(TransitionSupporterJourney::class)->handle($journey->fresh(), SupporterJourneyStatus::Scheduled, now()->addHour()->toAtomString(), $officer);
        expect(fn () => app(DispatchSupporterJourney::class)->handle($journey->fresh()))->toThrow(LogicException::class, 'not due');
        app(TransitionSupporterJourney::class)->handle($journey->fresh(), SupporterJourneyStatus::Paused, null, $officer);
        app(TransitionSupporterJourney::class)->handle($journey->fresh(), SupporterJourneyStatus::Scheduled, now()->addHour()->toAtomString(), $officer);

        app(RecordPartyConsent::class)->handle($party, [
            'purpose' => ConsentPurpose::SupporterUpdates,
            'channel' => ConsentChannel::Sms,
            'decision' => ConsentDecision::Withdrawn,
            'wording_version' => 'sms-v2',
            'wording' => 'Stop SMS supporter updates.',
            'source' => 'preference-centre',
            'occurred_at' => now()->addSecond()->toAtomString(),
        ], $officer);

        $this->travel(2)->hours();
        $recipient = app(DispatchSupporterJourney::class)->handle($journey->fresh())->sole();
        expect($recipient->status)->toBe(SupporterJourneyRecipientStatus::Cancelled)
            ->and($recipient->variant)->toBeIn(['A', 'B']);

        return $journey->fresh();
    });

    expect($journey->status)->toBe(SupporterJourneyStatus::Scheduled)
        ->and($journey->version)->toBeGreaterThanOrEqual(5);
});

it('publishes only configured reconciled aggregates and serves tenant-safe public impact', function () {
    [$organisation, $executive] = configurableOrganisation(OrganisationRole::ExecutiveViewer, 'configurable-impact');

    [$public, $board] = app(OrganisationContext::class)->run($organisation, function () use ($executive, $organisation): array {
        $attendee = Party::factory()->for($organisation)->create(['created_at' => now()->subDay()]);
        $attendee->businessRoles()->create(['organisation_id' => $organisation->id, 'role' => PartyBusinessRole::EventAttendee]);
        EventRegistration::factory()->create([
            'party_id' => $attendee->id,
            'status' => EventRegistrationStatus::Attended,
            'attended_at' => now()->subDay(),
        ]);
        $configuration = OrganisationConfiguration::factory()->create([
            'definition' => [
                'public_metric_ids' => ['engagement.event_attendance'],
                'pack_metric_ids' => ['engagement.event_attendance', 'data.missing_contact_rate'],
            ],
        ]);
        $filters = ['period_start' => now()->subDays(2)->toDateString(), 'period_end' => now()->addDay()->toDateString()];
        $public = app(PublishImpactSnapshot::class)->handle($organisation, $executive, 'public', $filters);
        $board = app(PublishImpactSnapshot::class)->handle($organisation, $executive, 'board', $filters);

        expect($configuration->status)->toBe(OrganisationConfigurationStatus::Active)
            ->and($public->metrics)->toHaveCount(1)
            ->and($public->metrics[0]['definition']['id'])->toBe('engagement.event_attendance')
            ->and(collect($public->cohort_comparisons)->firstWhere('cohort', PartyBusinessRole::EventAttendee->value)['metrics']['engagement.event_attendance']['availability'])->toBe('suppressed')
            ->and(app(BuildImpactReportPack::class)->handle($board)[0])->toBe(['section', 'cohort', 'metric', 'value', 'availability', 'unit', 'registry_version', 'period_start', 'period_end_exclusive'])
            ->and(fn () => $public->update(['audience' => 'board']))->toThrow(LogicException::class);

        return [$public, $board];
    });

    $other = Organisation::factory()->active()->create(['slug' => 'other-impact']);
    app(OrganisationContext::class)->run($other, fn () => PublishedImpactSnapshot::factory()->create([
        'audience' => 'public',
        'published_at' => now()->addMinute(),
        'metrics' => [['definition' => ['label' => 'Other tenant secret', 'formula' => 'secret', 'unit' => 'count'], 'value' => 99, 'availability' => 'available']],
    ]));

    $this->get('https://configurable-impact.community-kind.test/impact')
        ->assertOk()
        ->assertSee('Event attendance')
        ->assertDontSee('Other tenant secret');
    expect($public->published_at)->not->toBeNull()
        ->and($board->published_at)->toBeNull();
});

/** @return array{Organisation, User} */
function configurableOrganisation(OrganisationRole $role, ?string $slug = null): array
{
    $organisation = Organisation::factory()->active()->create($slug === null ? [] : ['slug' => $slug]);
    $user = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $user->id, 'role' => $role]);

    return [$organisation, $user];
}

function configurableSupporter(Organisation $organisation, User $actor, string $name, PartyBusinessRole $role, ConsentChannel $channel = ConsentChannel::Email): Party
{
    $party = Party::factory()->for($organisation)->create(['display_name' => $name]);
    $party->businessRoles()->create(['organisation_id' => $organisation->id, 'role' => $role]);
    $contactType = $channel === ConsentChannel::Email ? PartyContactType::Email : PartyContactType::Telephone;
    $contact = $channel === ConsentChannel::Email ? Str::slug($name).'@example.test' : '+27820000001';
    app(StorePartyContact::class)->handle($party, $contactType, $contact);
    app(RecordPartyConsent::class)->handle($party, [
        'purpose' => ConsentPurpose::SupporterUpdates,
        'channel' => $channel,
        'decision' => ConsentDecision::Granted,
        'wording_version' => 'v1',
        'wording' => 'I agree to supporter updates.',
        'source' => 'test-fixture',
        'occurred_at' => now()->subMinute()->toAtomString(),
    ], $actor);

    return $party;
}

function configurableSegment(PartyBusinessRole $role, string $activityType, ?int $recencyDays, int $minimumFrequency, ?int $minimumValue, ConsentChannel $channel = ConsentChannel::Email): AudienceSegment
{
    return AudienceSegment::factory()->create([
        'criteria' => [
            'purpose' => ConsentPurpose::SupporterUpdates->value,
            'channel' => $channel->value,
            'role' => $role->value,
            'service_area' => null,
            'interest' => null,
            'donation_activity' => $activityType === 'donation',
            'campaign_source' => null,
            'activity_type' => $activityType,
            'recency_days' => $recencyDays,
            'minimum_frequency' => $minimumFrequency,
            'minimum_value' => $minimumValue,
        ],
    ]);
}
