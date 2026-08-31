<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseGoalStatus;
use App\Enums\CaseMetricCode;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseGoal;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionCaseGoal
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly RecordCaseWorkflowTransition $recordTransition, private readonly RecordCaseMetric $recordMetric) {}

    public function handle(CaseGoal $goal, CaseGoalStatus $to, int $expectedVersion, CarbonInterface $effectiveAt, User $actor, ?string $reason = null): CaseGoal
    {
        $this->context->ensureOwns($goal->organisation_id);

        return DB::transaction(function () use ($goal, $to, $expectedVersion, $effectiveAt, $actor, $reason): CaseGoal {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($goal->service_case_id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot transition its work.');
            }
            $locked = CaseGoal::query()->lockForUpdate()->findOrFail($goal->id);

            if ($locked->status === $to) {
                return $locked;
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The goal changed while it was being reviewed.');
            }
            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition goal from {$locked->status->value} to {$to->value}.");
            }
            if ($to->isTerminal() && blank($reason)) {
                throw new LogicException('A terminal goal requires an outcome or reason code.');
            }

            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'version' => $locked->version + 1, 'effective_at' => $to->isTerminal() ? $effectiveAt : null, 'terminal_reason' => $to->isTerminal() ? $reason : null])->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Goal, $locked->id, $from->value, $to->value, $locked->version, $effectiveAt, $actor, $reason);

            $code = match ($to) {
                CaseGoalStatus::Achieved => CaseMetricCode::GoalAchieved, CaseGoalStatus::NotAchieved => CaseMetricCode::GoalNotAchieved, default => null
            };
            if ($code !== null) {
                $this->recordMetric->handle($case, $code, $effectiveAt, "goal:{$locked->id}:{$locked->version}");
            }

            return $locked->refresh();
        });
    }
}
