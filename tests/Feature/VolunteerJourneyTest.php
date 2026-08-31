<?php

use App\Actions\Portal\CancelSupporterRegistration;
use App\Actions\Reporting\BuildImpactDashboard;
use App\Actions\Volunteering\RecordVolunteerHours;
use App\Actions\Volunteering\TransitionVolunteerApplication;
use App\Actions\Volunteering\TransitionVolunteerAssignment;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerAssignmentStatus;
use App\Enums\VolunteerCredentialStatus;
use App\Enums\VolunteerOpportunityStatus;
use App\Enums\VolunteerShiftStatus;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\Models\PortalAccessGrant;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerCredential;
use App\Models\VolunteerHourEntry;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerShift;
use App\OrganisationContext;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('v', 32));
    config([
        'classified_data.encryption.current_version' => 'volunteer-data-v1',
        'classified_data.encryption.keys' => ['volunteer-data-v1' => $key],
        'classified_data.contact_index.current_version' => 'volunteer-index-v1',
        'classified_data.contact_index.previous_version' => null,
        'classified_data.contact_index.keys' => ['volunteer-index-v1' => $key],
    ]);
    Date::setTestNow('2026-06-15 12:00:00 UTC');
});

afterEach(fn () => Date::setTestNow());

it('registers only against the tenant public host and enforces opportunity capacity', function () {
    $organisation = Organisation::factory()->active()->create(['slug' => 'garden-kind']);
    $other = Organisation::factory()->active()->create(['slug' => 'other-kind']);
    $opportunity = app(OrganisationContext::class)->run($organisation, fn () => VolunteerOpportunity::factory()->create([
        'capacity' => 1,
        'status' => VolunteerOpportunityStatus::Published,
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addDay(),
    ]));
    $otherOpportunity = app(OrganisationContext::class)->run($other, fn () => VolunteerOpportunity::factory()->create(['status' => VolunteerOpportunityStatus::Published]));
    $url = "https://garden-kind.community-kind.test/volunteer/{$opportunity->id}";

    $this->post($url, ['name' => 'Amina Volunteer', 'email' => 'amina.volunteer@example.test', 'interests' => ['gardening'], 'availability' => ['Saturday'], 'consent_email' => true])
        ->assertOk()->assertSee('Registration received');
    $this->post($url, ['name' => 'Second Volunteer', 'email' => 'second.volunteer@example.test', 'interests' => [], 'availability' => ['Sunday'], 'consent_email' => false])
        ->assertConflict();
    $this->get("https://garden-kind.community-kind.test/volunteer/{$otherOpportunity->id}")->assertNotFound();

    app(OrganisationContext::class)->run($organisation, function () use ($opportunity): void {
        $application = VolunteerApplication::query()->sole();
        expect($application->volunteer_opportunity_id)->toBe($opportunity->id)
            ->and($application->follow_up_status)->toBe('eligible')
            ->and($application->party->businessRoles()->pluck('role')->all())->toContain(PartyBusinessRole::Volunteer)
            ->and(PartyContactPoint::query()->count())->toBe(1)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::VolunteerApplicationSubmitted)->exists())->toBeTrue();
    });
});

it('uses explicit audited onboarding assignment attendance and hours transitions', function () {
    [$organisation, $staff, $application, $shift] = volunteerWorkflowFixture();

    app(OrganisationContext::class)->run($organisation, function () use ($application, $shift, $staff): void {
        $transitionApplication = app(TransitionVolunteerApplication::class);
        $application = $transitionApplication->handle($application, VolunteerApplicationStatus::Onboarding, $staff);
        VolunteerCredential::factory()->create(['volunteer_application_id' => $application->id, 'party_id' => $application->party_id, 'status' => VolunteerCredentialStatus::Pending]);
        expect(fn () => $transitionApplication->handle($application, VolunteerApplicationStatus::Approved, $staff))
            ->toThrow(LogicException::class, 'must be current');

        $application->credentials()->update(['status' => VolunteerCredentialStatus::Verified, 'verified_at' => now(), 'expires_at' => now()->addYear()]);
        $application = $transitionApplication->handle($application, VolunteerApplicationStatus::Approved, $staff);
        $assignment = $application->assignments()->sole();
        expect($assignment->volunteer_shift_id)->toBe($shift->id);

        $assignment = app(TransitionVolunteerAssignment::class)->handle($assignment, VolunteerAssignmentStatus::Attended, $staff);
        $hours = app(RecordVolunteerHours::class)->handle($assignment, 180, $staff);
        expect(app(RecordVolunteerHours::class)->handle($assignment, 180, $staff)->is($hours))->toBeTrue()
            ->and(fn () => app(RecordVolunteerHours::class)->handle($assignment, 181, $staff))->toThrow(LogicException::class, 'already been recorded')
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::VolunteerApplicationTransitioned)->count())->toBe(2)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::VolunteerAssignmentTransitioned)->count())->toBe(2)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::VolunteerHoursRecorded)->count())->toBe(1);
    });
});

it('limits staff views to engagement officers and exposes no service records', function () {
    [$organisation, $engagement, $application] = volunteerWorkflowFixture();
    $caseWorker = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $caseWorker->id, 'role' => OrganisationRole::CaseWorker]);
    $url = route('volunteers.show', [$organisation, $application->volunteer_opportunity_id]);

    $this->actingAs($caseWorker)->get($url)->assertForbidden();
    $this->actingAs($engagement)->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('volunteers/show')
        ->where('opportunity.applications.0.name', 'Synthetic Volunteer Example')
        ->missing('opportunity.applications.0.cases')
        ->missing('opportunity.applications.0.roles')
        ->missing('opportunity.applications.0.safeContactInstructions'));
});

it('expires credentials explicitly with tenant audit evidence', function () {
    [$organisation, , $application] = volunteerWorkflowFixture();
    $credential = app(OrganisationContext::class)->run($organisation, fn () => VolunteerCredential::factory()->create([
        'volunteer_application_id' => $application->id,
        'party_id' => $application->party_id,
        'status' => VolunteerCredentialStatus::Verified,
        'expires_at' => now()->subMinute(),
    ]));

    $this->artisan('volunteers:expire-credentials')->assertSuccessful()->expectsOutput('Expired 1 volunteer credential(s).');

    app(OrganisationContext::class)->run($organisation, function () use ($credential): void {
        expect($credential->refresh()->status)->toBe(VolunteerCredentialStatus::Expired)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::VolunteerCredentialExpired)->where('subject_id', $credential->id)->exists())->toBeTrue();
    });
});

it('reconciles tenant-local volunteer applications and contribution hours', function () {
    [$organisation, $engagement, $application, $shift] = volunteerWorkflowFixture();
    $other = Organisation::factory()->active()->create();

    app(OrganisationContext::class)->run($organisation, function () use ($application, $shift): void {
        $assignment = VolunteerAssignment::factory()->create(['volunteer_shift_id' => $shift->id, 'volunteer_application_id' => $application->id, 'party_id' => $application->party_id, 'status' => VolunteerAssignmentStatus::Attended, 'attended_at' => now()]);
        VolunteerHourEntry::factory()->create(['volunteer_assignment_id' => $assignment->id, 'party_id' => $application->party_id, 'minutes' => 150, 'occurred_at' => now()]);
    });
    app(OrganisationContext::class)->run($other, function (): void {
        $opportunity = VolunteerOpportunity::factory()->create();
        $application = VolunteerApplication::factory()->create(['volunteer_opportunity_id' => $opportunity->id, 'submitted_at' => now()]);
        $shift = VolunteerShift::factory()->create(['volunteer_opportunity_id' => $opportunity->id]);
        $assignment = VolunteerAssignment::factory()->create(['volunteer_shift_id' => $shift->id, 'volunteer_application_id' => $application->id, 'party_id' => $application->party_id, 'status' => VolunteerAssignmentStatus::Attended, 'attended_at' => now()]);
        VolunteerHourEntry::factory()->create(['volunteer_assignment_id' => $assignment->id, 'party_id' => $application->party_id, 'minutes' => 600, 'occurred_at' => now()]);
    });

    $metrics = app(OrganisationContext::class)->run($organisation, fn () => collect(app(BuildImpactDashboard::class)->handle($engagement, $organisation, ['period_start' => '2026-06-01', 'period_end' => '2026-07-01'])['metrics'])->keyBy('definition.id'));
    expect($metrics['engagement.volunteer_applications']['value'])->toBe(1.0)
        ->and($metrics['engagement.volunteer_hours']['value'])->toBe(2.5);
});

it('withdraws volunteer work when the linked supporter cancels', function () {
    [$organisation, $staff, $application, $shift] = volunteerWorkflowFixture();
    $supporter = User::factory()->create();

    app(OrganisationContext::class)->run($organisation, function () use ($application, $shift, $staff, $supporter): void {
        $application->update(['status' => VolunteerApplicationStatus::Approved]);
        $application->registration->update(['status' => SupporterRegistrationStatus::Confirmed]);
        $assignment = VolunteerAssignment::factory()->create(['volunteer_shift_id' => $shift->id, 'volunteer_application_id' => $application->id, 'party_id' => $application->party_id]);
        $grant = PortalAccessGrant::factory()->create([
            'user_id' => $supporter->id,
            'person_party_id' => $application->party_id,
            'verified_at' => now(),
            'token_used_at' => now(),
            'created_by_user_id' => $staff->id,
        ]);

        app(CancelSupporterRegistration::class)->handle($grant, $application->registration);

        expect($application->refresh()->status)->toBe(VolunteerApplicationStatus::Withdrawn)
            ->and($assignment->refresh()->status)->toBe(VolunteerAssignmentStatus::Cancelled)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::SupporterRegistrationCancelled)->exists())->toBeTrue();
    });
});

/** @return array{Organisation, User, VolunteerApplication, VolunteerShift} */
function volunteerWorkflowFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $staff = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $staff->id, 'role' => OrganisationRole::EngagementOfficer]);

    return app(OrganisationContext::class)->run($organisation, function () use ($organisation, $staff): array {
        $party = Party::factory()->for($organisation)->create(['display_name' => 'Synthetic Volunteer Example']);
        $opportunity = VolunteerOpportunity::factory()->create(['status' => VolunteerOpportunityStatus::Published, 'registration_opens_at' => now()->subDay(), 'registration_closes_at' => now()->addDay()]);
        $application = VolunteerApplication::factory()->create(['volunteer_opportunity_id' => $opportunity->id, 'party_id' => $party->id, 'submitted_at' => now()]);
        $shift = VolunteerShift::factory()->create(['volunteer_opportunity_id' => $opportunity->id, 'status' => VolunteerShiftStatus::Open, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHours(4)]);

        return [$organisation, $staff, $application, $shift];
    });
}
