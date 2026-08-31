<?php

use App\Actions\CaseConfidentiality\GrantRestrictedAccess;
use App\Actions\Intake\AssignCaseWorker;
use App\Enums\ExternalReferralStatus;
use App\Enums\IntakeStatus;
use App\Enums\OrganisationRole;
use App\Enums\RestrictedAccessPermission;
use App\Enums\TenantAuditEventType;
use App\Models\CaseRiskAssessment;
use App\Models\CaseTask;
use App\Models\ExternalReferral;
use App\Models\IntakeRequest;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\ServiceCase;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

function monitoringCase(Organisation $organisation, Program $program, string $partyName): ServiceCase
{
    return app(OrganisationContext::class)->run($organisation, function () use ($organisation, $partyName, $program): ServiceCase {
        $party = Party::factory()->for($organisation)->create(['display_name' => $partyName]);
        $intake = IntakeRequest::factory()->create(['program_id' => $program->id, 'party_id' => $party->id]);

        return ServiceCase::factory()->create(['intake_request_id' => $intake->id, 'program_id' => $program->id, 'party_id' => $party->id]);
    });
}

it('reconciles role-scoped service work without exposing inaccessible or sensitive records', function () {
    $organisation = Organisation::factory()->active()->create();
    $manager = User::factory()->create();
    $worker = User::factory()->create();
    $managerMembership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);
    $workerMembership = $organisation->memberships()->create(['user_id' => $worker->id, 'role' => OrganisationRole::CaseWorker]);

    [$visibleProgram, $secondVisibleProgram, $hiddenProgram] = app(OrganisationContext::class)->run($organisation, fn (): array => [
        Program::factory()->for($organisation)->create(['name' => '=Visible Support']),
        Program::factory()->for($organisation)->create(['name' => 'Second Visible Support']),
        Program::factory()->for($organisation)->create(['name' => 'Hidden Support']),
    ]);
    app(OrganisationContext::class)->run($organisation, function () use ($managerMembership, $secondVisibleProgram, $visibleProgram, $workerMembership): void {
        $managerMembership->programs()->attach([$visibleProgram->id, $secondVisibleProgram->id]);
        $workerMembership->programs()->attach($visibleProgram);
    });
    $visibleCase = monitoringCase($organisation, $visibleProgram, 'Visible Client Name');
    $unassignedCase = monitoringCase($organisation, $visibleProgram, 'Unassigned Client Name');
    $hiddenCase = monitoringCase($organisation, $hiddenProgram, 'Hidden Client Name');

    app(OrganisationContext::class)->run($organisation, function () use ($hiddenCase, $manager, $visibleCase, $visibleProgram, $workerMembership): void {
        app(AssignCaseWorker::class)->handle($visibleCase, $workerMembership, $manager);
        CaseTask::factory()->create(['service_case_id' => $visibleCase->id, 'due_at' => now()->subDay()]);
        ExternalReferral::factory()->create(['service_case_id' => $visibleCase->id, 'status' => ExternalReferralStatus::Sent, 'effective_at' => now()->subHour()]);
        CaseRiskAssessment::factory()->create(['service_case_id' => $visibleCase->id, 'encrypted_content' => 'Protected visible risk narrative.']);
        CaseTask::factory()->create(['service_case_id' => $hiddenCase->id, 'due_at' => now()->subDay()]);
        $waitlistParty = Party::factory()->for($visibleProgram->organisation)->create(['display_name' => 'Waitlisted Client Name']);
        IntakeRequest::factory()->create(['program_id' => $visibleProgram->id, 'party_id' => $waitlistParty->id, 'status' => IntakeStatus::Waitlisted]);
    });

    $this->actingAs($manager)
        ->get(route('dashboard', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('serviceOperations.counts.caseload', 2)
            ->where('serviceOperations.counts.waitlist', 1)
            ->where('serviceOperations.counts.overdue', 1)
            ->where('serviceOperations.counts.risks', 0)
            ->where('serviceOperations.counts.referrals', 1)
            ->has('serviceOperations.caseload', 2));
    $this->actingAs($worker)
        ->get(route('dashboard', $organisation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('serviceOperations.counts.caseload', 1)
            ->where('serviceOperations.counts.waitlist', 0)
            ->where('serviceOperations.counts.overdue', 1)
            ->where('serviceOperations.counts.risks', 0)
            ->where('serviceOperations.counts.referrals', 1)
            ->where('serviceOperations.caseload.0.id', $visibleCase->id)
            ->missing('serviceOperations.caseload.1'));

    app(OrganisationContext::class)->run($organisation, fn () => app(GrantRestrictedAccess::class)->handle(
        $visibleCase, $managerMembership, RestrictedAccessPermission::SensitiveData, 'Safeguarding dashboard.', $manager,
    ));
    $this->actingAs($manager)
        ->get(route('dashboard', [$organisation, 'program_id' => $visibleProgram->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('serviceOperations.selectedProgramId', $visibleProgram->id)
            ->has('serviceOperations.programs', 2)
            ->where('serviceOperations.counts.risks', 1));
    $this->actingAs($manager)
        ->get(route('dashboard', [$organisation, 'program_id' => $hiddenProgram->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('serviceOperations.counts.caseload', 0)
            ->has('serviceOperations.programs', 2));

    $export = $this->actingAs($manager)->get(route('dashboard.service-operations.export', $organisation))->assertOk();
    expect($export->streamedContent())
        ->toContain($visibleCase->id)
        ->toContain($unassignedCase->id)
        ->not->toContain($hiddenCase->id)
        ->not->toContain('Visible Client Name')
        ->not->toContain('Unassigned Client Name')
        ->not->toContain('Hidden Client Name')
        ->not->toContain('risk narrative')
        ->toContain("'=Visible Support")
        ->not->toContain(',=Visible Support');
    app(OrganisationContext::class)->run($organisation, fn () => expect(TenantAuditEvent::query()->where('type', TenantAuditEventType::ServiceOperationsExported)->sole()->payload['record_count'])->toBe(6));
});
