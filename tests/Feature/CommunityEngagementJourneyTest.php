<?php

use App\Actions\Engagement\CreatePartnerProfile;
use App\Actions\Engagement\RecordPartnerCommitment;
use App\Actions\Engagement\TransitionEventRegistration;
use App\Actions\Engagement\TransitionInKindOffer;
use App\Actions\Reporting\BuildImpactDashboard;
use App\Enums\CommunityEventStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\InKindOfferStatus;
use App\Enums\OrganisationRole;
use App\Enums\PartnerCommitmentStatus;
use App\Enums\PartyKind;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\CommunityEvent;
use App\Models\EventRegistration;
use App\Models\InKindOffer;
use App\Models\Organisation;
use App\Models\PartnerCommitment;
use App\Models\PartnerProfile;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('e', 32));
    config(['classified_data.encryption.current_version' => 'engagement-data-v1', 'classified_data.encryption.keys' => ['engagement-data-v1' => $key], 'classified_data.contact_index.current_version' => 'engagement-index-v1', 'classified_data.contact_index.previous_version' => null, 'classified_data.contact_index.keys' => ['engagement-index-v1' => $key]]);
    Date::setTestNow('2026-06-15 12:00:00 UTC');
});

afterEach(fn () => Date::setTestNow());

it('uses the public tenant host and waitlists registrations after capacity is reached', function () {
    [$organisation, $staff, $event] = communityEngagementFixture(1);
    $other = Organisation::factory()->active()->create(['slug' => 'other-events']);
    $url = "https://{$organisation->slug}.community-kind.test/events/{$event->id}";
    $payload = fn (string $name, string $email): array => ['name' => $name, 'email' => $email, 'consent_email' => true];

    $this->post($url, $payload('First Attendee', 'first.attendee@example.test'))->assertOk()->assertSee('Registration confirmed');
    $this->post($url, $payload('Second Attendee', 'second.attendee@example.test'))->assertOk()->assertSee('Added to the waitlist');
    $this->get("https://{$other->slug}.community-kind.test/events/{$event->id}")->assertNotFound();

    app(OrganisationContext::class)->run($organisation, function () use ($staff): void {
        [$confirmed, $waitlisted] = EventRegistration::query()->orderBy('registered_at')->get();
        expect([$confirmed->status, $waitlisted->status])->toBe([EventRegistrationStatus::Confirmed, EventRegistrationStatus::Waitlisted]);

        $transition = app(TransitionEventRegistration::class);
        $transition->handle($confirmed, EventRegistrationStatus::Cancelled, $staff);
        $promoted = $transition->handle($waitlisted, EventRegistrationStatus::Confirmed, $staff);

        expect($promoted->status)->toBe(EventRegistrationStatus::Confirmed)
            ->and($promoted->registration->status)->toBe(SupporterRegistrationStatus::Confirmed);
    });
});

it('audits reminder attendance cancellation and consent-aware follow-up transitions', function () {
    [$organisation, $staff, $event] = communityEngagementFixture();
    $url = "https://{$organisation->slug}.community-kind.test/events/{$event->id}";
    $this->post($url, ['name' => 'Event Attendee', 'email' => 'attendee@example.test', 'consent_email' => true])->assertOk();
    $this->post($url, ['name' => 'Private Attendee', 'email' => 'private.attendee@example.test', 'consent_email' => false])->assertOk();

    app(OrganisationContext::class)->run($organisation, function () use ($staff): void {
        [$registration, $privateRegistration] = EventRegistration::query()->orderBy('registered_at')->get();
        $transition = app(TransitionEventRegistration::class);
        $registration = $transition->remind($registration, $staff);
        expect(fn () => $transition->remind($registration, $staff))->toThrow(LogicException::class, 'unreminded confirmed');
        $registration = $transition->handle($registration, EventRegistrationStatus::Attended, $staff);
        $registration = $transition->handle($registration, EventRegistrationStatus::FollowedUp, $staff);
        $privateRegistration = $transition->handle($privateRegistration, EventRegistrationStatus::Attended, $staff);
        expect($registration->followed_up_at)->not->toBeNull()
            ->and(fn () => $transition->handle($privateRegistration, EventRegistrationStatus::FollowedUp, $staff))->toThrow(LogicException::class, 'suppressed by consent')
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::EventReminderRecorded)->count())->toBe(1)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::EventRegistrationTransitioned)->count())->toBe(5);
    });
});

it('preserves and fulfils a tenant-local in-kind offer', function () {
    [$organisation, $staff] = communityEngagementFixture();
    $url = "https://{$organisation->slug}.community-kind.test/in-kind";
    $this->post($url, ['name' => 'Goods Contributor', 'email' => 'goods@example.test', 'category' => 'Blankets', 'description' => 'Warm winter blankets', 'quantity' => 12.5, 'unit' => 'boxes', 'estimated_value_minor' => 25000, 'currency' => 'zar', 'condition' => 'new', 'consent_email' => false])->assertOk()->assertSee('Offer received');

    app(OrganisationContext::class)->run($organisation, function () use ($staff): void {
        $offer = InKindOffer::query()->sole();
        expect($offer->quantity)->toBe('12.50')->and($offer->estimated_value_minor)->toBe(25000)->and($offer->currency)->toBe('ZAR');
        $offer = app(TransitionInKindOffer::class)->handle($offer, InKindOfferStatus::Accepted, null, $staff);
        $offer = app(TransitionInKindOffer::class)->handle($offer, InKindOfferStatus::Fulfilled, 'All blankets received and allocated.', $staff);
        expect($offer->fulfilment_outcome)->toBe('All blankets received and allocated.')
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::InKindOfferTransitioned)->count())->toBe(3);
    });
});

it('records partner contacts relationships commitments and history without creating authorization identities', function () {
    [$organisation, $staff] = communityEngagementFixture();
    $userCount = User::query()->count();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $staff, $userCount): void {
        $profile = app(CreatePartnerProfile::class)->handle($organisation, ['name' => 'Synthetic Community Hub', 'email' => 'hub@example.test', 'telephone' => '+27 10 555 0101', 'partner_type' => 'community_hub', 'relationship_summary' => 'Hosts monthly outreach and provides meeting rooms.'], $staff);
        app(RecordPartnerCommitment::class)->handle($profile, ['title' => 'Host monthly outreach', 'details' => 'Provide an accessible room on the first Saturday.', 'status' => PartnerCommitmentStatus::Planned, 'due_on' => '2026-07-01'], $staff);
        expect($profile->party->kind)->toBe(PartyKind::Organisation)
            ->and($profile->party->contactPoints()->count())->toBe(2)
            ->and($profile->commitments()->count())->toBe(1)
            ->and($profile->party->timelineEvents()->count())->toBe(2)
            ->and(User::query()->count())->toBe($userCount);
    });
});

it('authorizes engagement staff and reconciles all three journeys without tenant leakage', function () {
    [$organisation, $engagement] = communityEngagementFixture();
    $caseWorker = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $caseWorker->id, 'role' => OrganisationRole::CaseWorker]);
    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $engagement): void {
        $event = CommunityEvent::factory()->create();
        EventRegistration::factory()->create(['community_event_id' => $event->id, 'status' => EventRegistrationStatus::Attended, 'attended_at' => now()]);
        InKindOffer::factory()->create(['status' => InKindOfferStatus::Fulfilled, 'fulfilled_at' => now()]);
        $partner = PartnerProfile::factory()->create();
        PartnerCommitment::factory()->create(['partner_profile_id' => $partner->id, 'created_at' => now()]);
        $metrics = collect(app(BuildImpactDashboard::class)->handle($engagement, $organisation, ['period_start' => '2026-06-01', 'period_end' => '2026-07-01'])['metrics'])->keyBy('definition.id');
        expect($metrics['engagement.event_attendance']['value'])->toBe(1.0)->and($metrics['engagement.in_kind_fulfilments']['value'])->toBe(1.0)->and($metrics['engagement.partner_commitments']['value'])->toBe(1.0);
    });
    $url = route('community-engagement.index', $organisation);
    $this->actingAs($caseWorker)->get($url)->assertForbidden();
    $this->actingAs($engagement)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('community-engagement/index')->missing('cases')->missing('memberships'));
});

/** @return array{Organisation, User, CommunityEvent} */
function communityEngagementFixture(int $capacity = 10): array
{
    $organisation = Organisation::factory()->active()->create(['slug' => fake()->unique()->slug(2)]);
    $staff = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $staff->id, 'role' => OrganisationRole::EngagementOfficer]);
    $event = app(OrganisationContext::class)->run($organisation, fn () => CommunityEvent::factory()->create(['capacity' => $capacity, 'status' => CommunityEventStatus::Published]));

    return [$organisation, $staff, $event];
}
