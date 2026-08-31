<?php

namespace App\Actions\Intake;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\CaseAssignmentRole;
use App\Enums\CaseAssignmentStatus;
use App\Enums\OrganisationRole;
use App\Enums\TenantAuditEventType;
use App\Models\CaseAssignment;
use App\Models\Membership;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class AssignCaseWorker
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly RecordTenantAuditEvent $recordTenantAuditEvent,
    ) {}

    public function handle(ServiceCase $case, Membership $worker, User $actor, ?string $reason = null): CaseAssignment
    {
        $this->organisationContext->ensureOwns($case->organisation_id);
        $this->organisationContext->ensureOwns($worker->organisation_id);

        if (! $worker->user->hasOrganisationRole($case->organisation, OrganisationRole::CaseWorker, $case->program)
            || ! $worker->user->hasProgramAccess($case->program)) {
            throw new LogicException('The selected worker is not authorised for this Program.');
        }

        return DB::transaction(function () use ($case, $worker, $actor, $reason): CaseAssignment {
            $lockedCase = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $active = CaseAssignment::query()
                ->where('service_case_id', $lockedCase->id)
                ->where('role', CaseAssignmentRole::Primary)
                ->where('status', CaseAssignmentStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($active?->membership_id === $worker->id) {
                return $active;
            }

            if ($active !== null) {
                $active->update([
                    'status' => CaseAssignmentStatus::Ended,
                    'active_primary_marker' => null,
                    'ended_at' => now(),
                    'ended_reason' => $reason,
                    'ended_by_user_id' => $actor->id,
                ]);
            }

            $assignment = CaseAssignment::query()->create([
                'organisation_id' => $case->organisation_id,
                'service_case_id' => $lockedCase->id,
                'membership_id' => $worker->id,
                'role' => CaseAssignmentRole::Primary,
                'status' => CaseAssignmentStatus::Active,
                'active_primary_marker' => true,
                'started_at' => now(),
                'assigned_reason' => $reason,
                'assigned_by_user_id' => $actor->id,
            ]);
            $this->recordTenantAuditEvent->handle(
                $case->organisation,
                TenantAuditEventType::CaseAssigned,
                'service_case',
                $case->id,
                ['case_id' => $case->id, 'membership_id' => $worker->id],
                $actor,
            );

            return $assignment;
        });
    }
}
