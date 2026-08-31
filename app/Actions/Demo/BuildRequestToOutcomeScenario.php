<?php

namespace App\Actions\Demo;

use App\Actions\CaseConfidentiality\GrantRestrictedAccess;
use App\Actions\CaseConfidentiality\ReclassifyServiceCase;
use App\Actions\CaseConfidentiality\RecordCaseRiskAssessment;
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
use App\Actions\Intake\CreateIntakeRequest;
use App\Actions\Intake\TransitionIntakeRequest;
use App\Actions\Parties\StoreSafeContactInstruction;
use App\Enums\CaseAppointmentStatus;
use App\Enums\CaseClassification;
use App\Enums\CaseGoalStatus;
use App\Enums\CaseServiceStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\EligibilityStatus;
use App\Enums\ExternalReferralStatus;
use App\Enums\IntakeStatus;
use App\Enums\IntakeUrgency;
use App\Enums\RestrictedAccessPermission;
use App\Enums\ServiceCaseStatus;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;

class BuildRequestToOutcomeScenario
{
    public function __construct(
        private readonly CreateIntakeRequest $createIntake,
        private readonly TransitionIntakeRequest $transitionIntake,
        private readonly TransitionServiceCase $transitionCase,
        private readonly CreateCaseGoal $createGoal,
        private readonly TransitionCaseGoal $transitionGoal,
        private readonly CreateCaseService $createService,
        private readonly TransitionCaseService $transitionService,
        private readonly CreateExternalReferral $createReferral,
        private readonly TransitionExternalReferral $transitionReferral,
        private readonly CreateCaseTask $createTask,
        private readonly TransitionCaseTask $transitionTask,
        private readonly CreateCaseAppointment $createAppointment,
        private readonly TransitionCaseAppointment $transitionAppointment,
        private readonly RecordCaseInteraction $recordInteraction,
        private readonly SaveCaseNote $saveNote,
        private readonly FinalizeCaseNote $finalizeNote,
        private readonly GrantRestrictedAccess $grantRestrictedAccess,
        private readonly ReclassifyServiceCase $reclassifyCase,
        private readonly RecordCaseRiskAssessment $recordRisk,
        private readonly StoreSafeContactInstruction $storeSafeContact,
    ) {}

    public function handle(Organisation $organisation, Program $program, Party $party, User $manager, Membership $workerMembership): ServiceCase
    {
        $existing = IntakeRequest::query()->where('idempotency_key', 'scenario-request-to-outcome-v1')->first();
        if ($existing?->serviceCase !== null) {
            $this->ensureConfidentialityFixture($existing->serviceCase, $party, $manager, $workerMembership);

            return $existing->serviceCase;
        }

        $reportingNow = Date::getTestNow();

        try {
            $worker = $workerMembership->user;
            Date::setTestNow($this->at('2026-06-02 08:00'));
            $intake = $this->createIntake->handle($organisation, $program, $party, [
                'source' => 'partner_referral',
                'narrative' => 'Synthetic referral from a fictional neighbourhood advice partner.',
                'presenting_needs' => 'Amina needs tenancy advice and stable housing support.',
                'intake_fields' => ['preferred_contact_time' => 'Weekday mornings', 'current_situation' => 'Temporarily staying with fictional friends.'],
                'eligibility_context' => ['service_area' => true, 'program_fit' => true],
                'risk_flags' => ['housing_loss'],
                'email' => 'amina.client@harbourkind.example.test',
                'telephone' => '+1 202-555-0111',
                'idempotency_key' => 'scenario-request-to-outcome-v1',
                'consent_granted' => true,
                'consent_source' => 'verbal',
            ], $manager);
            $this->transitionIntake->handle($intake, IntakeStatus::Submitted, 1, $manager);
            $this->transitionIntake->handle($intake->refresh(), IntakeStatus::UnderReview, 2, $manager, triage: [
                'urgency' => IntakeUrgency::Priority,
                'eligibility_status' => EligibilityStatus::Eligible,
                'eligibility_context' => ['service_area' => true, 'program_fit' => true],
                'risk_flags' => ['housing_loss'],
            ]);
            $this->transitionIntake->handle($intake->refresh(), IntakeStatus::Accepted, 3, $manager, worker: $workerMembership);
            $case = $intake->refresh()->serviceCase()->firstOrFail();
            $this->ensureConfidentialityFixture($case, $party, $manager, $workerMembership);

            Date::setTestNow($this->at('2026-06-03 09:00'));
            $this->transitionCase->handle($case, ServiceCaseStatus::Active, 1, now(), $manager);
            $goal = $this->createGoal->handle($case, 'Secure stable housing', 'Find a sustainable fictional tenancy.', $this->at('2026-06-25 17:00'), $worker);
            $this->transitionGoal->handle($goal, CaseGoalStatus::Active, 1, now(), $worker);
            Date::setTestNow($this->at('2026-06-04 09:00'));
            $this->recordInteraction->handle($case, 'telephone', 'Confirmed fictional appointment details.', now(), $worker);
            $service = $this->createService->handle($case, 'housing_advice', 'Fictional tenancy advice session.', $this->at('2026-06-10 11:00'), $worker);
            $this->transitionService->handle($service, CaseServiceStatus::Scheduled, 1, now(), $worker);
            Date::setTestNow($this->at('2026-06-05 09:00'));
            $referral = $this->createReferral->handle($case, 'Synthetic Housing Partner', 'Tenancy placement', 'Name and safe contact preference only', 'service_consent', $worker);
            $this->transitionReferral->handle($referral, ExternalReferralStatus::Sent, 1, now(), $worker);
            Date::setTestNow($this->at('2026-06-06 09:00'));
            $this->transitionReferral->handle($referral->refresh(), ExternalReferralStatus::Acknowledged, 2, now(), $worker);
            Date::setTestNow($this->at('2026-06-07 09:00'));
            $task = $this->createTask->handle($case, 'Confirm tenancy', 'Check the fictional signed agreement.', $this->at('2026-06-20 17:00'), $worker);
            Date::setTestNow($this->at('2026-06-08 09:00'));
            $appointment = $this->createAppointment->handle($case, 'Housing review', 'HarbourKind office', $this->at('2026-06-10 11:00'), $worker);
            Date::setTestNow($this->at('2026-06-10 12:00'));
            $this->transitionService->handle($service->refresh(), CaseServiceStatus::Completed, 2, now(), $worker);
            $this->transitionAppointment->handle($appointment, CaseAppointmentStatus::Completed, 1, now(), $worker, completedService: $service->refresh());
            Date::setTestNow($this->at('2026-06-12 09:00'));
            $this->transitionReferral->handle($referral->refresh(), ExternalReferralStatus::Connected, 3, now(), $worker);
            Date::setTestNow($this->at('2026-06-18 10:00'));
            $this->transitionTask->handle($task, CaseTaskStatus::Completed, 1, now(), $worker);
            Date::setTestNow($this->at('2026-06-27 14:00'));
            $this->transitionGoal->handle($goal->refresh(), CaseGoalStatus::Achieved, 2, now(), $worker, 'stable_tenancy');
            $note = $this->saveNote->handle($case, 'Synthetic confidential note: tenancy options reviewed.', $worker);
            $this->finalizeNote->handle($note, 1, $worker);
            Date::setTestNow($this->at('2026-06-28 15:00'));
            $this->transitionCase->handle($case->refresh(), ServiceCaseStatus::Closed, 2, now(), $manager, [
                'reason' => 'goals_completed',
                'narrative' => 'Stable fictional housing was secured and follow-up agreed.',
                'measures' => ['progress' => 4],
                'follow_up_at' => $this->at('2026-07-28 09:00'),
            ]);

            return $case->refresh();
        } finally {
            Date::setTestNow($reportingNow);
        }
    }

    private function at(string $localTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localTime, 'Africa/Johannesburg')->utc();
    }

    private function ensureConfidentialityFixture(ServiceCase $case, Party $party, User $manager, Membership $workerMembership): void
    {
        $managerMembership = $manager->organisationMembership($case->organisation);
        if ($managerMembership === null) {
            throw new \LogicException('The demo Program manager requires an Organisation Membership.');
        }

        foreach ([
            [$managerMembership, RestrictedAccessPermission::SensitiveData, $case->id, 'Synthetic safeguarding access.'],
            [$workerMembership, RestrictedAccessPermission::SensitiveData, $case->id, 'Assigned worker safeguarding access.'],
            [$managerMembership, RestrictedAccessPermission::IdentifiableCaseExport, null, 'Synthetic Program export fixture.'],
        ] as [$membership, $permission, $serviceCaseId, $reason]) {
            $exists = RestrictedAccessGrant::query()
                ->active()
                ->where('membership_id', $membership->id)
                ->where('program_id', $case->program_id)
                ->where('permission', $permission)
                ->where('service_case_id', $serviceCaseId)
                ->exists();

            if (! $exists) {
                $this->grantRestrictedAccess->handle($case, $membership, $permission, $reason, $manager);
            }
        }

        if ($case->confidentiality !== CaseClassification::HighlyRestricted) {
            $this->reclassifyCase->handle($case, CaseClassification::HighlyRestricted, 'Synthetic detailed risk fixture.', $manager);
        }

        if (! $case->riskAssessments()->exists()) {
            $this->recordRisk->handle($case->refresh(), 'Synthetic non-graphic risk assessment for authorization testing.', $manager);
        }

        if (! $party->safeContactInstructions()->whereNull('ended_at')->exists()) {
            $this->storeSafeContact->handle($party, [
                'instruction' => 'Do not leave voicemail.',
                'source' => 'synthetic_client_preference',
                'effective_at' => now()->toAtomString(),
            ], $manager);
        }
    }
}
