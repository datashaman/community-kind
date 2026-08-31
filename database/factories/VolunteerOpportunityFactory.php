<?php

namespace Database\Factories;

use App\Enums\VolunteerOpportunityStatus;
use App\Models\VolunteerOpportunity;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerOpportunity>
 */
class VolunteerOpportunityFactory extends Factory
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
            'title' => fake()->sentence(3),
            'summary' => fake()->paragraph(),
            'interest_tags' => ['community', 'food'],
            'capacity' => 20,
            'status' => VolunteerOpportunityStatus::Published,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addWeek(),
            'starts_at' => now()->addWeeks(2),
            'ends_at' => now()->addWeeks(3),
            'published_at' => now()->subDay(),
        ];
    }
}
