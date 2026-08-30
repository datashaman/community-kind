<?php

namespace Database\Factories;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\OrganisationInvitationRoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationInvitationRoleAssignment>
 */
class OrganisationInvitationRoleAssignmentFactory extends Factory
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
            'organisation_invitation_id' => function (array $attributes): int {
                return OrganisationInvitation::factory()->create([
                    'organisation_id' => $attributes['organisation_id'],
                    'invited_by' => User::factory(),
                ])->id;
            },
            'role' => OrganisationRole::EngagementOfficer,
            'program_id' => null,
        ];
    }
}
