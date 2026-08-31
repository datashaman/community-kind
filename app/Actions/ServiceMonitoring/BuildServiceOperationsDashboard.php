<?php

namespace App\Actions\ServiceMonitoring;

use App\Authorization\CaseAccess;
use App\Enums\CaseAssignmentStatus;
use App\Enums\CaseTaskStatus;
use App\Enums\ExternalReferralStatus;
use App\Enums\IntakeStatus;
use App\Enums\OrganisationRole;
use App\Enums\ServiceCaseStatus;
use App\Models\CaseRiskAssessment;
use App\Models\CaseTask;
use App\Models\ExternalReferral;
use App\Models\IntakeRequest;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\ServiceCase;
use App\Models\User;

final class BuildServiceOperationsDashboard
{
    public function __construct(private CaseAccess $caseAccess) {}

    /** @return array<string, mixed> */
    public function handle(User $user, Organisation $organisation, ?int $requestedProgramId = null): array
    {
        $membership = $user->organisationMembership($organisation);

        if ($membership === null || $membership->isHeld()) {
            return $this->empty();
        }

        $programs = Program::query()->select(['id', 'organisation_id', 'name'])->orderBy('name')->get();
        $managerProgramIds = $programs
            ->filter(fn (Program $program): bool => $user->hasOrganisationRole($organisation, OrganisationRole::ProgramManager, $program) && $user->hasProgramAccess($program))
            ->pluck('id');
        $workerProgramIds = $programs
            ->filter(fn (Program $program): bool => $user->hasOrganisationRole($organisation, OrganisationRole::CaseWorker, $program) && $user->hasProgramAccess($program))
            ->pluck('id');
        $allowedProgramIds = $managerProgramIds->merge($workerProgramIds)->unique()->values();
        $allowedPrograms = $programs->whereIn('id', $allowedProgramIds)->values();

        if ($requestedProgramId !== null) {
            $allowedProgramIds = $allowedProgramIds->filter(fn (int $id): bool => $id === $requestedProgramId)->values();
        }

        $cases = ServiceCase::query()
            ->select(['id', 'organisation_id', 'program_id', 'status', 'confidentiality'])
            ->with('program:id,organisation_id,name')
            ->whereIn('program_id', $allowedProgramIds)
            ->whereIn('status', [ServiceCaseStatus::Open, ServiceCaseStatus::Active, ServiceCaseStatus::OnHold])
            ->when(
                $managerProgramIds->intersect($allowedProgramIds)->isEmpty(),
                fn ($query) => $query->whereHas('assignments', fn ($assignment) => $assignment
                    ->where('membership_id', $membership->id)
                    ->where('status', CaseAssignmentStatus::Active)),
                fn ($query) => $query->where(function ($scope) use ($managerProgramIds, $allowedProgramIds, $membership): void {
                    $managed = $managerProgramIds->intersect($allowedProgramIds);
                    $scope->whereIn('program_id', $managed)
                        ->orWhereHas('assignments', fn ($assignment) => $assignment
                            ->where('membership_id', $membership->id)
                            ->where('status', CaseAssignmentStatus::Active));
                }),
            )
            ->get()
            ->filter(fn (ServiceCase $case): bool => $this->caseAccess->canView($user, $case))
            ->values();
        $caseIds = $cases->pluck('id');
        $casePrograms = $cases->mapWithKeys(fn (ServiceCase $case): array => [$case->id => $case->program->name]);
        $sensitiveCaseIds = $cases->filter(fn (ServiceCase $case): bool => $this->caseAccess->canViewSensitive($user, $case))->pluck('id');

        $waitlist = $managerProgramIds->intersect($allowedProgramIds)->isEmpty()
            ? collect()
            : IntakeRequest::query()
                ->select(['id', 'program_id', 'status', 'urgency', 'created_at'])
                ->with('program:id,organisation_id,name')
                ->whereIn('program_id', $managerProgramIds->intersect($allowedProgramIds))
                ->where('status', IntakeStatus::Waitlisted)
                ->oldest('created_at')
                ->get()
                ->map(fn (IntakeRequest $intake): array => [
                    'id' => $intake->id,
                    'program' => $intake->program->name,
                    'status' => $intake->status->value,
                    'urgency' => $intake->urgency->value,
                    'since' => $intake->created_at?->toAtomString(),
                ]);
        $overdue = CaseTask::query()
            ->select(['id', 'service_case_id', 'status', 'due_at'])
            ->whereIn('service_case_id', $caseIds)
            ->where('status', CaseTaskStatus::Open)
            ->where('due_at', '<', now())
            ->oldest('due_at')
            ->get()
            ->map(fn (CaseTask $task): array => [
                'id' => $task->id,
                'caseId' => $task->service_case_id,
                'program' => $casePrograms[$task->service_case_id],
                'dueAt' => $task->due_at?->toAtomString(),
            ]);
        $risks = CaseRiskAssessment::query()
            ->select(['id', 'service_case_id', 'effective_at'])
            ->whereIn('service_case_id', $sensitiveCaseIds)
            ->whereNull('ended_at')
            ->latest('effective_at')
            ->get()
            ->map(fn (CaseRiskAssessment $risk): array => [
                'id' => $risk->id,
                'caseId' => $risk->service_case_id,
                'program' => $casePrograms[$risk->service_case_id],
                'effectiveAt' => $risk->effective_at->toAtomString(),
            ]);
        $referrals = ExternalReferral::query()
            ->select(['id', 'service_case_id', 'status', 'effective_at'])
            ->whereIn('service_case_id', $caseIds)
            ->whereIn('status', [ExternalReferralStatus::Draft, ExternalReferralStatus::Sent, ExternalReferralStatus::Acknowledged])
            ->latest('effective_at')
            ->get()
            ->map(fn (ExternalReferral $referral): array => [
                'id' => $referral->id,
                'caseId' => $referral->service_case_id,
                'program' => $casePrograms[$referral->service_case_id],
                'status' => $referral->status->value,
                'effectiveAt' => $referral->effective_at?->toAtomString(),
            ]);

        return [
            'programs' => $allowedPrograms->map(fn (Program $program): array => ['id' => $program->id, 'name' => $program->name]),
            'selectedProgramId' => $requestedProgramId,
            'counts' => ['caseload' => $cases->count(), 'waitlist' => $waitlist->count(), 'overdue' => $overdue->count(), 'risks' => $risks->count(), 'referrals' => $referrals->count()],
            'caseload' => $cases->map(fn (ServiceCase $case): array => ['id' => $case->id, 'program' => $case->program->name, 'status' => $case->status->value])->values(),
            'waitlist' => $waitlist->values(),
            'overdue' => $overdue->values(),
            'risks' => $risks->values(),
            'referrals' => $referrals->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return ['programs' => [], 'selectedProgramId' => null, 'counts' => ['caseload' => 0, 'waitlist' => 0, 'overdue' => 0, 'risks' => 0, 'referrals' => 0], 'caseload' => [], 'waitlist' => [], 'overdue' => [], 'risks' => [], 'referrals' => []];
    }
}
