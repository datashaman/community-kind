<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseWorkflowSubject;
use App\Models\CaseWorkflowTransition;
use App\Models\ServiceCase;
use App\Models\User;
use Carbon\CarbonInterface;

class RecordCaseWorkflowTransition
{
    public function handle(
        ServiceCase $case,
        CaseWorkflowSubject $subjectType,
        string $subjectId,
        ?string $from,
        string $to,
        int $version,
        CarbonInterface $effectiveAt,
        User $actor,
        ?string $reason = null,
    ): CaseWorkflowTransition {
        return CaseWorkflowTransition::query()->create([
            'organisation_id' => $case->organisation_id,
            'service_case_id' => $case->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'from_status' => $from,
            'to_status' => $to,
            'reason' => $reason,
            'effective_at' => $effectiveAt,
            'recorded_at' => now(),
            'version' => $version,
            'actor_user_id' => $actor->id,
        ]);
    }
}
