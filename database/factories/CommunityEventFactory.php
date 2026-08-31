<?php

namespace Database\Factories;

use App\Enums\CommunityEventStatus;
use App\Models\CommunityEvent;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityEvent>
 */
class CommunityEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'title' => fake()->sentence(3), 'summary' => fake()->paragraph(), 'capacity' => 20, 'status' => CommunityEventStatus::Published, 'registration_opens_at' => now()->subDay(), 'registration_closes_at' => now()->addWeek(), 'starts_at' => now()->addWeeks(2), 'ends_at' => now()->addWeeks(2)->addHours(2), 'published_at' => now()];
    }
}
