<?php

namespace Database\Factories;

use App\Enums\CaseWorkflowSubject;
use App\Models\ServiceCase;
use App\Models\WorkflowCorrection;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<WorkflowCorrection> */
class WorkflowCorrectionFactory extends Factory
{
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'subject_type' => CaseWorkflowSubject::Service, 'subject_id' => Str::uuid()->toString(), 'correction_type' => 'correction', 'reason' => 'data_entry_error', 'replacement_values' => ['effective_at' => now()->toAtomString()], 'effective_at' => now(), 'recorded_at' => now()];
    }
}
