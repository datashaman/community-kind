<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseAppointmentStatus;
use App\Enums\CaseServiceStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseAppointment;
use App\Models\CaseService;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionCaseAppointment
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(CaseAppointment $appointment, CaseAppointmentStatus $to, int $expectedVersion, CarbonInterface $effectiveAt, User $actor, ?string $reason = null, ?CaseService $completedService = null): CaseAppointment
    {
        $this->context->ensureOwns($appointment->organisation_id);

        return DB::transaction(function () use ($appointment, $to, $expectedVersion, $effectiveAt, $actor, $reason, $completedService): CaseAppointment {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($appointment->service_case_id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot transition its work.');
            }
            $locked = CaseAppointment::query()->lockForUpdate()->findOrFail($appointment->id);

            if ($locked->status === $to) {
                return $locked;
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The appointment changed while it was being reviewed.');
            }
            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition appointment from {$locked->status->value} to {$to->value}.");
            }
            if (in_array($to, [CaseAppointmentStatus::Cancelled, CaseAppointmentStatus::NoShow], true) && blank($reason)) {
                throw new LogicException('A cancelled or no-show appointment requires a reason code.');
            }
            if ($completedService !== null && ($to !== CaseAppointmentStatus::Completed || $completedService->service_case_id !== $locked->service_case_id || $completedService->status !== CaseServiceStatus::Completed)) {
                throw new LogicException('A completed appointment may only link a completed service from the same Case.');
            }

            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'version' => $locked->version + 1, 'effective_at' => $effectiveAt, 'terminal_reason' => $reason, 'completed_service_id' => $completedService?->id])->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Appointment, $locked->id, $from->value, $to->value, $locked->version, $effectiveAt, $actor, $reason);

            return $locked->refresh();
        });
    }
}
