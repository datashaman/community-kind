<?php

namespace Database\Factories;

use App\Enums\CaseAssignmentRole;
use App\Enums\CaseAssignmentStatus;
use App\Models\CaseAssignment;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseAssignment>
 */
class CaseAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'service_case_id' => ServiceCase::factory(),
            'membership_id' => fn (): int => app(OrganisationContext::class)->organisation()
                ->memberships()->create(['user_id' => User::factory()->create()->id])->id,
            'role' => CaseAssignmentRole::Primary,
            'status' => CaseAssignmentStatus::Active,
            'active_primary_marker' => true,
            'started_at' => now(),
        ];
    }
}
