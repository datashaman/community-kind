<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseTaskStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseTask;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionCaseTask
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(CaseTask $task, CaseTaskStatus $to, int $expectedVersion, CarbonInterface $effectiveAt, User $actor, ?string $reason = null): CaseTask
    {
        $this->context->ensureOwns($task->organisation_id);

        return DB::transaction(function () use ($task, $to, $expectedVersion, $effectiveAt, $actor, $reason): CaseTask {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($task->service_case_id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot transition its work.');
            }
            $locked = CaseTask::query()->lockForUpdate()->findOrFail($task->id);

            if ($locked->status === $to) {
                return $locked;
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The task changed while it was being reviewed.');
            }
            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition task from {$locked->status->value} to {$to->value}.");
            }
            if ($to === CaseTaskStatus::Cancelled && blank($reason)) {
                throw new LogicException('A cancelled task requires a reason code.');
            }

            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'version' => $locked->version + 1, 'effective_at' => $effectiveAt, 'terminal_reason' => $reason])->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Task, $locked->id, $from->value, $to->value, $locked->version, $effectiveAt, $actor, $reason);

            return $locked->refresh();
        });
    }
}
