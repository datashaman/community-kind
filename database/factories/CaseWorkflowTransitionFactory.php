<?php

namespace Database\Factories;

use App\Enums\CaseWorkflowSubject;
use App\Models\CaseWorkflowTransition;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<CaseWorkflowTransition> */
class CaseWorkflowTransitionFactory extends Factory
{
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'service_case_id' => ServiceCase::factory(), 'subject_type' => CaseWorkflowSubject::Goal, 'subject_id' => Str::uuid()->toString(), 'from_status' => 'draft', 'to_status' => 'active', 'effective_at' => now(), 'recorded_at' => now(), 'version' => 2];
    }
}
