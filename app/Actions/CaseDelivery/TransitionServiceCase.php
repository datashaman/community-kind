<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseAssignmentRole;
use App\Enums\CaseAssignmentStatus;
use App\Enums\CaseMetricCode;
use App\Enums\CaseWorkflowSubject;
use App\Enums\ServiceCaseStatus;
use App\Models\CaseOutcome;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionServiceCase
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly EnsureCanManageCase $access,
        private readonly ClassifiedDataEncrypter $encrypter,
        private readonly RecordCaseWorkflowTransition $recordTransition,
        private readonly RecordCaseMetric $recordMetric,
    ) {}

    /** @param array{reason?: string, narrative?: string, measures?: array<string, int|float|string>, follow_up_at?: CarbonInterface|null} $closure */
    public function handle(ServiceCase $case, ServiceCaseStatus $to, int $expectedVersion, CarbonInterface $effectiveAt, User $actor, array $closure = []): ServiceCase
    {
        $this->context->ensureOwns($case->organisation_id);

        return DB::transaction(function () use ($case, $to, $expectedVersion, $effectiveAt, $actor, $closure): ServiceCase {
            $locked = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($locked, $actor);

            if ($locked->status === $to) {
                return $locked;
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The Case changed while it was being reviewed.');
            }
            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition Case from {$locked->status->value} to {$to->value}.");
            }

            if (in_array($to, [ServiceCaseStatus::Active, ServiceCaseStatus::OnHold], true) && ! $this->hasPrimaryAssignment($locked)) {
                throw new LogicException('An active primary case-worker assignment is required.');
            }

            if ($to === ServiceCaseStatus::Cancelled) {
                if (blank($closure['reason'] ?? null)) {
                    throw new LogicException('Cancelling a Case requires a reason code.');
                }
                if ($locked->services()->where('status', 'completed')->exists() || $locked->interactions()->exists()) {
                    throw new LogicException('A Case with substantive service must be closed, not cancelled.');
                }
                if ($locked->goals()->whereIn('status', ['draft', 'active'])->exists()
                    || $locked->services()->whereIn('status', ['planned', 'scheduled'])->exists()
                    || $locked->referrals()->whereIn('status', ['draft', 'sent', 'acknowledged'])->exists()
                    || $locked->tasks()->where('status', 'open')->exists()
                    || $locked->appointments()->where('status', 'scheduled')->exists()) {
                    throw new LogicException('Resolve every open Case item before cancellation.');
                }
            }

            $checklist = null;
            if ($to === ServiceCaseStatus::Closed) {
                $checklist = $this->closeCase($locked, $closure, $effectiveAt, $actor);
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => $to,
                'version' => $locked->version + 1,
                'closed_at' => $to->isTerminal() ? $effectiveAt : null,
                'closure_reason' => $to->isTerminal() ? ($closure['reason'] ?? null) : null,
                'follow_up_at' => $to === ServiceCaseStatus::Closed ? ($closure['follow_up_at'] ?? null) : null,
                'closure_checklist' => $checklist,
            ])->save();
            $this->recordTransition->handle($locked, CaseWorkflowSubject::CaseRecord, $locked->id, $from->value, $to->value, $locked->version, $effectiveAt, $actor, $closure['reason'] ?? null);

            if ($to === ServiceCaseStatus::Closed) {
                $this->recordMetric->handle($locked, CaseMetricCode::CaseClosed, $effectiveAt, "case:{$locked->id}:{$locked->version}", ['closure_reason' => $locked->closure_reason]);
            }

            return $locked->refresh();
        });
    }

    /**
     * @param  array{reason?: string, narrative?: string, measures?: array<string, int|float|string>, follow_up_at?: CarbonInterface|null}  $closure
     * @return array{goals_terminal: bool, services_terminal: bool, appointments_terminal: bool, tasks_terminal: bool, referrals_resolved_or_carried: bool}
     */
    private function closeCase(ServiceCase $case, array $closure, CarbonInterface $effectiveAt, User $actor): array
    {
        if (blank($closure['reason'] ?? null) || blank($closure['narrative'] ?? null) || ! is_array($closure['measures'] ?? null)) {
            throw new LogicException('Case closure requires a reason and structured outcome.');
        }

        $requiredMeasures = $case->program
            ->outcomeMeasures()
            ->whereNull('retired_at')
            ->pluck('key')
            ->all();
        $providedMeasures = array_keys($closure['measures']);
        sort($requiredMeasures);
        sort($providedMeasures);
        if ($requiredMeasures !== $providedMeasures || collect($closure['measures'])->contains(fn (mixed $value): bool => ! is_numeric($value))) {
            throw new LogicException('Every configured outcome measure is required.');
        }
        if (($closure['follow_up_at'] ?? null) instanceof CarbonInterface && $closure['follow_up_at']->lessThanOrEqualTo($effectiveAt)) {
            throw new LogicException('A follow-up date must be after Case closure.');
        }

        $checks = [
            'goals_terminal' => ! $case->goals()->whereIn('status', ['draft', 'active'])->exists(),
            'services_terminal' => ! $case->services()->whereIn('status', ['planned', 'scheduled'])->exists(),
            'appointments_terminal' => ! $case->appointments()->where('status', 'scheduled')->exists(),
            'tasks_terminal' => ! $case->tasks()->where('status', 'open')->exists(),
            'referrals_resolved_or_carried' => ! $case->referrals()->whereIn('status', ['draft', 'sent', 'acknowledged'])->whereNull('carried_forward_at')->exists(),
        ];

        if (in_array(false, $checks, true)) {
            throw new LogicException('Complete, cancel, resolve, or carry forward every open Case item before closure.');
        }

        $outcome = new CaseOutcome;
        $measures = array_map(fn (int|float|string $value): int|float => is_int($value) || is_float($value) ? $value : (float) $value, $closure['measures']);
        $outcome->forceFill(['id' => $outcome->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'measures' => $measures, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'effective_at' => $effectiveAt, 'recorded_at' => now(), 'recorded_by_user_id' => $actor->id]);
        $outcome->encrypted_content = new ClassifiedValue($closure['narrative']);
        $outcome->save();

        return $checks;
    }

    private function hasPrimaryAssignment(ServiceCase $case): bool
    {
        return $case->assignments()->where('role', CaseAssignmentRole::Primary)->where('status', CaseAssignmentStatus::Active)->exists();
    }
}
