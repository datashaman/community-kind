<?php

namespace Database\Factories;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleAssignment>
 */
class RoleAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'membership_id' => function (array $attributes): int {
                $organisation = Organisation::query()->whereKey((int) $attributes['organisation_id'])->firstOrFail();

                return $organisation->memberships()->create([
                    'user_id' => User::factory()->create()->id,
                ])->id;
            },
            'role' => OrganisationRole::CaseWorker,
            'program_id' => null,
            'ended_at' => null,
        ];
    }
}
