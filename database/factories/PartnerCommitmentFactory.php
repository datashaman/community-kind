<?php

namespace Database\Factories;

use App\Enums\PartnerCommitmentStatus;
use App\Models\PartnerCommitment;
use App\Models\PartnerProfile;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCommitment>
 */
class PartnerCommitmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'partner_profile_id' => PartnerProfile::factory(), 'title' => fake()->sentence(3), 'details' => fake()->sentence(), 'status' => PartnerCommitmentStatus::Planned, 'due_on' => now()->addMonth()];
    }
}
