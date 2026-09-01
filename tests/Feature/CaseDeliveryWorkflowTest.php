<?php

use App\Actions\CaseDelivery\CarryForwardExternalReferral;
use App\Actions\CaseDelivery\CreateCaseAppointment;
use App\Actions\CaseDelivery\CreateCaseGoal;
use App\Actions\CaseDelivery\CreateCaseService;
use App\Actions\CaseDelivery\CreateCaseTask;
use App\Actions\CaseDelivery\CreateExternalReferral;
use App\Actions\CaseDelivery\FinalizeCaseNote;
use App\Actions\CaseDelivery\RecordCaseInteraction;
use App\Actions\CaseDelivery\SaveCaseNote;
use App\Actions\CaseDelivery\TransitionCaseAppointment;
use App\Actions\CaseDelivery\TransitionCaseGoal;
use App\Actions\CaseDelivery\TransitionCaseService;
use App\Actions\CaseDelivery\TransitionCaseTask;
use App\Actions\CaseDelivery\TransitionExternalReferral;
use App\Actions\CaseDelivery\TransitionServiceCase;
use App\Actions\Intake\AssignCaseWorker;
use App\Actions\Intake\CreateIntakeRequest;
use App\Actions\Parties\RecordPartyConsent;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseAppointmentStatus;
use App\Enums\CaseGoalStatus;
use App\Enums\CaseMetricCode;
use App\Enums\CaseServiceStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\ExternalReferralStatus;
use App\Enums\OrganisationRole;
use App\Enums\ServiceCaseStatus;
use App\Models\CaseWorkflowTransition;
use App\Models\Membership;
use App\Models\MetricEvent;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{organisation: Organisation, program: Program, party: Party, manager: User, worker: User, workerMembership: Membership, case: ServiceCase} */
function caseDeliveryFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $manager = User::factory()->create();
    $worker = User::factory()->create();
    $managerMembership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);
    $workerMembership = $organisation->memberships()->create(['user_id' => $worker->id, 'role' => OrganisationRole::CaseWorker]);

    [$program, $party, $case] = app(OrganisationContext::class)->run($organisation, function () use ($organisation, $manager, $managerMembership, $workerMembership): array {
        $program = Program::factory()->for($organisation)->create(['configuration' => [
            'intake_fields' => [], 'eligibility_fields' => [], 'risk_flags' => [],
        ]]);
        $program->outcomeMeasures()->create(['key' => 'housing_stability', 'label' => 'Housing stability', 'unit' => 'score', 'position' => 0]);
        $managerMembership->programs()->attach($program);
        $workerMembership->programs()->attach($program);
        $party = Party::factory()->for($organisation)->create();
        $party->programs()->attach($program);
        app(RecordPartyConsent::class)->handle($party, [
            'purpose' => ConsentPurpose::Service,
            'decision' => ConsentDecision::Granted,
            'wording_version' => 'test-v1',
            'wording' => 'Synthetic test consent.',
            'source' => 'test',
            'occurred_at' => '2026-06-01T09:00:00+02:00',
        ], $manager);
        $intake = app(CreateIntakeRequest::class)->handle($organisation, $program, $party, [
            'source' => 'test',
            'narrative' => 'Synthetic test request.',
            'presenting_needs' => 'Housing support.',
            'intake_fields' => [],
            'eligibility_context' => [],
            'risk_flags' => [],
            'email' => null,
            'telephone' => null,
            'idempotency_key' => null,
            'consent_granted' => true,
            'consent_source' => 'test',
        ], $manager);
        $case = ServiceCase::factory()->create(['organisation_id' => $organisation->id, 'intake_request_id' => $intake->id, 'program_id' => $program->id, 'party_id' => $party->id, 'opened_at' => '2026-06-02 08:00:00']);

        return [$program, $party, $case];
    });

    return compact('organisation', 'program', 'party', 'manager', 'worker', 'workerMembership', 'case');
}

it('defines every required valid state path explicitly', function () {
    expect(ServiceCaseStatus::Open->allowedTransitions())->toEqualCanonicalizing([ServiceCaseStatus::Active, ServiceCaseStatus::OnHold, ServiceCaseStatus::Closed, ServiceCaseStatus::Cancelled])
        ->and(ServiceCaseStatus::Active->allowedTransitions())->toEqualCanonicalizing([ServiceCaseStatus::OnHold, ServiceCaseStatus::Closed])
        ->and(ServiceCaseStatus::OnHold->allowedTransitions())->toEqualCanonicalizing([ServiceCaseStatus::Active, ServiceCaseStatus::Closed])
        ->and(CaseGoalStatus::Draft->allowedTransitions())->toEqualCanonicalizing([CaseGoalStatus::Active, CaseGoalStatus::Cancelled])
        ->and(CaseGoalStatus::Active->allowedTransitions())->toEqualCanonicalizing([CaseGoalStatus::Achieved, CaseGoalStatus::NotAchieved, CaseGoalStatus::Cancelled, CaseGoalStatus::Withdrawn])
        ->and(CaseServiceStatus::Planned->allowedTransitions())->toEqualCanonicalizing([CaseServiceStatus::Scheduled, CaseServiceStatus::Completed, CaseServiceStatus::Cancelled])
        ->and(CaseServiceStatus::Scheduled->allowedTransitions())->toEqualCanonicalizing([CaseServiceStatus::Completed, CaseServiceStatus::Cancelled, CaseServiceStatus::NotDelivered])
        ->and(ExternalReferralStatus::Draft->allowedTransitions())->toEqualCanonicalizing([ExternalReferralStatus::Sent, ExternalReferralStatus::Cancelled])
        ->and(ExternalReferralStatus::Sent->allowedTransitions())->toEqualCanonicalizing([ExternalReferralStatus::Acknowledged, ExternalReferralStatus::Connected, ExternalReferralStatus::NotConnected, ExternalReferralStatus::Cancelled])
        ->and(ExternalReferralStatus::Acknowledged->allowedTransitions())->toEqualCanonicalizing([ExternalReferralStatus::Connected, ExternalReferralStatus::NotConnected, ExternalReferralStatus::Cancelled])
        ->and(CaseTaskStatus::Open->allowedTransitions())->toEqualCanonicalizing([CaseTaskStatus::Completed, CaseTaskStatus::Cancelled])
        ->and(CaseAppointmentStatus::Scheduled->allowedTransitions())->toEqualCanonicalizing([CaseAppointmentStatus::Completed, CaseAppointmentStatus::Cancelled, CaseAppointmentStatus::NoShow]);
});

it('delivers and closes a complete case atomically with immutable history and reconciled metric dates', function () {
    $fixture = caseDeliveryFixture();
    $at = fn (string $time): CarbonImmutable => CarbonImmutable::parse($time, 'Africa/Johannesburg')->utc();

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture, $at): void {
        expect(fn () => app(TransitionServiceCase::class)->handle($fixture['case'], ServiceCaseStatus::Active, 1, $at('2026-06-03 09:00'), $fixture['manager']))
            ->toThrow(LogicException::class, 'primary case-worker assignment');
        app(AssignCaseWorker::class)->handle($fixture['case'], $fixture['workerMembership'], $fixture['manager']);
        app(TransitionServiceCase::class)->handle($fixture['case'], ServiceCaseStatus::Active, 1, $at('2026-06-03 09:00'), $fixture['manager']);

        $goal = app(CreateCaseGoal::class)->handle($fixture['case'], 'Secure stable housing', 'Find a sustainable tenancy.', $at('2026-06-25 17:00'), $fixture['worker']);
        app(TransitionCaseGoal::class)->handle($goal, CaseGoalStatus::Active, 1, $at('2026-06-03 10:00'), $fixture['worker']);
        app(TransitionCaseGoal::class)->handle($goal->refresh(), CaseGoalStatus::Achieved, 2, $at('2026-06-27 14:00'), $fixture['worker'], 'stable_tenancy');

        $service = app(CreateCaseService::class)->handle($fixture['case'], 'housing_advice', 'Tenancy advice session.', $at('2026-06-10 11:00'), $fixture['worker']);
        app(TransitionCaseService::class)->handle($service, CaseServiceStatus::Scheduled, 1, $at('2026-06-04 09:00'), $fixture['worker']);
        app(TransitionCaseService::class)->handle($service->refresh(), CaseServiceStatus::Completed, 2, $at('2026-06-10 12:00'), $fixture['worker']);

        $referral = app(CreateExternalReferral::class)->handle($fixture['case'], 'Synthetic Housing Partner', 'Tenancy placement', 'Name and contact preference only', 'service_consent', $fixture['worker']);
        app(TransitionExternalReferral::class)->handle($referral, ExternalReferralStatus::Sent, 1, $at('2026-06-05 09:00'), $fixture['worker']);
        app(TransitionExternalReferral::class)->handle($referral->refresh(), ExternalReferralStatus::Acknowledged, 2, $at('2026-06-06 09:00'), $fixture['worker']);
        app(TransitionExternalReferral::class)->handle($referral->refresh(), ExternalReferralStatus::Connected, 3, $at('2026-06-12 09:00'), $fixture['worker']);

        $task = app(CreateCaseTask::class)->handle($fixture['case'], 'Confirm tenancy', 'Check signed agreement.', $at('2026-06-20 17:00'), $fixture['worker']);
        app(TransitionCaseTask::class)->handle($task, CaseTaskStatus::Completed, 1, $at('2026-06-18 10:00'), $fixture['worker']);
        $appointment = app(CreateCaseAppointment::class)->handle($fixture['case'], 'Housing review', 'HarbourKind office', $at('2026-06-10 11:00'), $fixture['worker']);
        app(TransitionCaseAppointment::class)->handle($appointment, CaseAppointmentStatus::Completed, 1, $at('2026-06-10 12:00'), $fixture['worker'], completedService: $service->refresh());
        app(RecordCaseInteraction::class)->handle($fixture['case'], 'telephone', 'Confirmed appointment details.', $at('2026-06-04 12:00'), $fixture['worker']);
        $note = app(SaveCaseNote::class)->handle($fixture['case'], 'Synthetic confidential case note.', $fixture['worker']);
        $finalizedNote = app(FinalizeCaseNote::class)->handle($note, 1, $fixture['worker']);
        expect(fn () => $finalizedNote->update(['encrypted_content' => new ClassifiedValue('Attempted rewrite')]))
            ->toThrow(LogicException::class, 'cannot be overwritten');
        $addendum = app(SaveCaseNote::class)->handle($fixture['case'], 'Correction without overwriting the original.', $fixture['worker'], $note->refresh());
        app(FinalizeCaseNote::class)->handle($addendum, 1, $fixture['worker']);

        $closed = app(TransitionServiceCase::class)->handle($fixture['case']->refresh(), ServiceCaseStatus::Closed, 2, $at('2026-06-28 15:00'), $fixture['manager'], [
            'reason' => 'goals_completed',
            'narrative' => 'Stable housing was secured in this fictional scenario.',
            'measures' => ['housing_stability' => 4],
            'follow_up_at' => $at('2026-07-28 09:00'),
        ]);

        expect($closed->status)->toBe(ServiceCaseStatus::Closed)
            ->and($closed->closure_checklist)->not->toContain(false)
            ->and($closed->outcome->measures)->toBe(['housing_stability' => 4])
            ->and(MetricEvent::query()->where('code', CaseMetricCode::ServiceDelivered)->firstOrFail()->occurred_at->equalTo($at('2026-06-10 12:00')))->toBeTrue()
            ->and(MetricEvent::query()->where('code', CaseMetricCode::CaseClosed)->firstOrFail()->occurred_at->equalTo($at('2026-06-28 15:00')))->toBeTrue();
        expect(DB::table('case_outcomes')->where('service_case_id', $closed->id)->value('encrypted_content'))->not->toContain('Stable housing');
        expect(fn () => DB::table('case_workflow_transitions')->where('service_case_id', $closed->id)->update(['reason' => 'rewritten']))->toThrow(QueryException::class);
    });
});

it('rejects stale and invalid transitions without partial writes', function () {
    $fixture = caseDeliveryFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        $goal = app(CreateCaseGoal::class)->handle($fixture['case'], 'Goal', 'Details', null, $fixture['manager']);
        $transitionCount = CaseWorkflowTransition::query()->count();
        expect(fn () => app(TransitionCaseGoal::class)->handle($goal, CaseGoalStatus::Achieved, 1, now(), $fixture['manager'], 'outcome'))
            ->toThrow(LogicException::class, 'Cannot transition')
            ->and(fn () => app(TransitionCaseGoal::class)->handle($goal, CaseGoalStatus::Active, 99, now(), $fixture['manager']))
            ->toThrow(LogicException::class, 'changed while');
        expect($goal->refresh()->status)->toBe(CaseGoalStatus::Draft)
            ->and(CaseWorkflowTransition::query()->count())->toBe($transitionCount);
    });
});

it('blocks closure until open work is terminal or a pending referral is carried forward with a reason', function () {
    $fixture = caseDeliveryFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        app(AssignCaseWorker::class)->handle($fixture['case'], $fixture['workerMembership'], $fixture['manager']);
        app(TransitionServiceCase::class)->handle($fixture['case'], ServiceCaseStatus::Active, 1, now(), $fixture['manager']);
        $task = app(CreateCaseTask::class)->handle($fixture['case'], 'Open task', 'Still pending', now()->addDay(), $fixture['worker']);
        $referral = app(CreateExternalReferral::class)->handle($fixture['case'], 'Synthetic Partner', 'Support', 'Contact preference', 'service_consent', $fixture['worker']);
        app(TransitionExternalReferral::class)->handle($referral, ExternalReferralStatus::Sent, 1, now(), $fixture['worker']);
        $closure = ['reason' => 'completed', 'narrative' => 'Outcome', 'measures' => ['housing_stability' => 3]];

        expect(fn () => app(TransitionServiceCase::class)->handle($fixture['case']->refresh(), ServiceCaseStatus::Closed, 2, now(), $fixture['manager'], $closure))
            ->toThrow(LogicException::class, 'Complete, cancel, resolve');
        expect($fixture['case']->refresh()->status)->toBe(ServiceCaseStatus::Active)->and($fixture['case']->outcome()->exists())->toBeFalse();

        app(TransitionCaseTask::class)->handle($task, CaseTaskStatus::Cancelled, 1, now(), $fixture['worker'], 'no_longer_needed');
        app(CarryForwardExternalReferral::class)->handle($referral->refresh(), 2, 'follow_up_in_new_case', now(), $fixture['worker']);
        expect(fn () => app(TransitionServiceCase::class)->handle($fixture['case']->refresh(), ServiceCaseStatus::Closed, 2, now(), $fixture['manager'], [...$closure, 'measures' => ['housing_stability' => 3, 'unexpected' => 1]]))
            ->toThrow(LogicException::class, 'configured outcome measure');
        expect(fn () => app(TransitionServiceCase::class)->handle($fixture['case']->refresh(), ServiceCaseStatus::Closed, 2, now(), $fixture['manager'], [...$closure, 'follow_up_at' => now()->subDay()]))
            ->toThrow(LogicException::class, 'after Case closure');
        app(TransitionServiceCase::class)->handle($fixture['case']->refresh(), ServiceCaseStatus::Closed, 2, now(), $fixture['manager'], $closure);
        expect($fixture['case']->refresh()->status)->toBe(ServiceCaseStatus::Closed);
    });
});

it('only cancels cases without substantive service or unresolved work', function () {
    $fixture = caseDeliveryFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        $service = app(CreateCaseService::class)->handle($fixture['case'], 'planned_support', 'Planned but not delivered.', null, $fixture['manager']);
        expect(fn () => app(TransitionServiceCase::class)->handle($fixture['case'], ServiceCaseStatus::Cancelled, 1, now(), $fixture['manager'], ['reason' => 'created_in_error']))
            ->toThrow(LogicException::class, 'Resolve every open');
        app(TransitionCaseService::class)->handle($service, CaseServiceStatus::Cancelled, 1, now(), $fixture['manager'], 'client_cancelled');
        app(TransitionServiceCase::class)->handle($fixture['case']->refresh(), ServiceCaseStatus::Cancelled, 1, now(), $fixture['manager'], ['reason' => 'created_in_error']);
        expect($fixture['case']->refresh()->status)->toBe(ServiceCaseStatus::Cancelled);
    });
});

it('rechecks sharing authority when an external referral is sent', function () {
    $fixture = caseDeliveryFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        $referral = app(CreateExternalReferral::class)->handle($fixture['case'], 'Synthetic Partner', 'Housing support', 'Name only', 'service_consent', $fixture['manager']);
        app(RecordPartyConsent::class)->handle($fixture['party'], [
            'purpose' => ConsentPurpose::Service,
            'decision' => ConsentDecision::Withdrawn,
            'wording_version' => 'test-v1',
            'wording' => 'Synthetic test consent.',
            'source' => 'test',
            'occurred_at' => now()->addMinute()->toAtomString(),
        ], $fixture['manager']);

        expect(fn () => app(TransitionExternalReferral::class)->handle($referral, ExternalReferralStatus::Sent, 1, now()->addMinutes(2), $fixture['manager']))
            ->toThrow(LogicException::class, 'current service-consent');
        expect($referral->refresh()->status)->toBe(ExternalReferralStatus::Draft);
    });
});

it('enforces tenant, Program, role, and active-assignment boundaries on the case workspace', function () {
    $fixture = caseDeliveryFixture();
    $administrator = User::factory()->create();
    $fixture['organisation']->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $otherOrganisation = Organisation::factory()->active()->create();
    $otherManager = User::factory()->create();
    $otherOrganisation->memberships()->create(['user_id' => $otherManager->id, 'role' => OrganisationRole::ProgramManager]);

    $this->actingAs($fixture['worker'])->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))->assertForbidden();
    $this->actingAs($administrator)->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))->assertForbidden();
    $this->actingAs($otherManager)->get(route('cases.show', [$otherOrganisation, $fixture['case']]))->assertNotFound();
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect(fn () => app(CreateCaseGoal::class)->handle($fixture['case'], 'Forbidden goal', 'No assignment.', null, $fixture['worker']))
        ->toThrow(LogicException::class, 'not authorised'));

    app(OrganisationContext::class)->run($fixture['organisation'], fn () => app(AssignCaseWorker::class)->handle($fixture['case'], $fixture['workerMembership'], $fixture['manager']));
    $this->actingAs($fixture['worker'])
        ->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('cases/show')->where('caseRecord.id', $fixture['case']->id)->where('canUpdate', true));
    $this->actingAs($fixture['worker'])->post(route('cases.items.store', [$fixture['organisation'], $fixture['case']]), [
        'kind' => 'goal',
        'title' => 'Worker-owned goal',
        'description' => 'A goal in the assigned case.',
    ])->assertRedirect()->assertSessionHasNoErrors();
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect($fixture['case']->goals()->count())->toBe(1));
});
