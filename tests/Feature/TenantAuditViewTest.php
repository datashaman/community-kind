<?php

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\OrganisationRole;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia;

it('projects only policy-permitted events without payloads or cross-tenant records', function () {
    $organisation = Organisation::factory()->active()->create();
    $otherOrganisation = Organisation::factory()->active()->create();
    $manager = User::factory()->create();
    $otherActor = User::factory()->create();
    $membership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);
    [$visibleProgram, $hiddenProgram] = app(OrganisationContext::class)->run($organisation, fn (): array => [
        Program::factory()->for($organisation)->create(['name' => 'Visible program']),
        Program::factory()->for($organisation)->create(['name' => 'Hidden program']),
    ]);
    app(OrganisationContext::class)->run($organisation, function () use ($hiddenProgram, $manager, $membership, $organisation, $otherActor, $visibleProgram): void {
        $membership->programs()->attach($visibleProgram);
        $record = app(RecordTenantAuditEvent::class);
        $record->handle($organisation, TenantAuditEventType::ServiceOperationsExported, 'service_operations', 'visible-subject', [
            'program_id' => $visibleProgram->id,
            'record_count' => 3,
        ], $otherActor);
        $record->handle($organisation, TenantAuditEventType::ServiceOperationsExported, 'service_operations', 'hidden-subject', [
            'program_id' => $hiddenProgram->id,
            'record_count' => 99,
        ], $otherActor);
        $record->handle($organisation, TenantAuditEventType::ProgramUpdated, 'program', (string) $visibleProgram->id, [
            'program_id' => $visibleProgram->id,
            'changed_fields' => ['name'],
        ], $otherActor);
        $record->handle($organisation, TenantAuditEventType::DonationCreated, 'donation', 'donation-subject', [
            'donation_id' => 'donation-secret',
            'frequency' => 'once',
        ], $manager);
    });
    app(OrganisationContext::class)->run($otherOrganisation, fn () => app(RecordTenantAuditEvent::class)->handle(
        $otherOrganisation,
        TenantAuditEventType::ProgramUpdated,
        'program',
        'other-tenant-subject',
        ['program_id' => 999, 'changed_fields' => ['name']],
        $otherActor,
    ));

    $this->actingAs($manager)->get(route('audit.index', $organisation))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('audit/index')
            ->has('events', 2)
            ->missing('events.0.payload'));

    app(OrganisationContext::class)->run($organisation, function (): void {
        $projection = TenantAuditEvent::query()->where('type', TenantAuditEventType::AuditViewAccessed)->sole();
        expect($projection->payload)->toMatchArray([
            'record_count' => 2,
            'scope' => 'policy_projection',
            'role' => OrganisationRole::ProgramManager->value,
        ]);
    });
});

it('applies role and domain boundaries and denies executive viewers', function () {
    $organisation = Organisation::factory()->active()->create();
    $administrator = User::factory()->create();
    $engagement = User::factory()->create();
    $executive = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $engagement->id, 'role' => OrganisationRole::EngagementOfficer]);
    $organisation->memberships()->create(['user_id' => $executive->id, 'role' => OrganisationRole::ExecutiveViewer]);
    app(OrganisationContext::class)->run($organisation, function () use ($administrator, $engagement, $organisation): void {
        $record = app(RecordTenantAuditEvent::class);
        $record->handle($organisation, TenantAuditEventType::ProgramUpdated, 'program', 'program-subject', [
            'program_id' => 1,
            'changed_fields' => ['name'],
        ], $administrator);
        $record->handle($organisation, TenantAuditEventType::DonationCreated, 'donation', 'donation-subject', [
            'donation_id' => 'opaque-donation',
            'frequency' => 'once',
        ], $engagement);
    });

    $this->actingAs($administrator)->get(route('audit.index', $organisation))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1)->where('events.0.domain', 'configuration'));
    $this->actingAs($engagement)->get(route('audit.index', $organisation))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('events', 1)->where('events.0.domain', 'fundraising'));
    $this->actingAs($executive)->get(route('audit.index', $organisation))->assertForbidden();
});
