<?php

namespace Database\Factories;

use App\Enums\VolunteerShiftStatus;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerShift;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerShift>
 */
class VolunteerShiftFactory extends Factory
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
            'volunteer_opportunity_id' => VolunteerOpportunity::factory(),
            'title' => 'Community shift',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(4),
            'capacity' => 10,
            'status' => VolunteerShiftStatus::Open,
        ];
    }
}
