<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseMetricCode;
use App\Enums\CaseWorkflowSubject;
use App\Models\ServiceCase;
use App\Models\User;
use App\Models\WorkflowCorrection;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class CorrectCaseWorkflowRecord
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly RecordCaseMetric $recordMetric) {}

    /** @param array<string, bool|int|float|string|null> $replacementValues */
    public function handle(ServiceCase $case, string $subjectId, string $subjectType, string $correctionType, string $reason, array $replacementValues, CarbonInterface $effectiveAt, User $actor, ?CaseMetricCode $metricCode = null): WorkflowCorrection
    {
        $this->context->ensureOwns($case->organisation_id);

        if (! in_array($correctionType, ['correction', 'reversal', 'carry_forward'], true) || blank($reason)) {
            throw new LogicException('A supported correction type and reason code are required.');
        }
        if (array_diff(array_keys($replacementValues), ['effective_at', 'status', 'metric_value', 'service_code']) !== []) {
            throw new LogicException('Correction values may only contain approved non-sensitive fields.');
        }

        return DB::transaction(function () use ($case, $subjectId, $subjectType, $correctionType, $reason, $replacementValues, $effectiveAt, $actor, $metricCode): WorkflowCorrection {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            $subjectExists = match ($subjectType) {
                'case' => hash_equals($case->id, $subjectId),
                'goal' => $case->goals()->whereKey($subjectId)->exists(),
                'service' => $case->services()->whereKey($subjectId)->exists(),
                'referral' => $case->referrals()->whereKey($subjectId)->exists(),
                'task' => $case->tasks()->whereKey($subjectId)->exists(),
                'appointment' => $case->appointments()->whereKey($subjectId)->exists(),
                'note' => $case->notes()->whereKey($subjectId)->exists(),
                default => false,
            };
            if (! $subjectExists) {
                throw new LogicException('The correction subject does not belong to this Case.');
            }
            $correction = WorkflowCorrection::query()->create([
                'organisation_id' => $case->organisation_id,
                'service_case_id' => $case->id,
                'subject_type' => CaseWorkflowSubject::from($subjectType),
                'subject_id' => $subjectId,
                'correction_type' => $correctionType,
                'reason' => $reason,
                'replacement_values' => $replacementValues,
                'effective_at' => $effectiveAt,
                'recorded_at' => now(),
                'actor_user_id' => $actor->id,
            ]);

            if ($metricCode !== null && $correctionType === 'reversal') {
                $this->recordMetric->handle($case, $metricCode, $effectiveAt, "correction:{$correction->id}", ['correction' => true], -1);
            }

            return $correction;
        });
    }
}
