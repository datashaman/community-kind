<?php

namespace Database\Factories;

use App\Enums\VolunteerAssignmentStatus;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerHourEntry;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerHourEntry>
 */
class VolunteerHourEntryFactory extends Factory
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
            'volunteer_assignment_id' => VolunteerAssignment::factory()->state(['status' => VolunteerAssignmentStatus::Attended, 'attended_at' => now()]),
            'party_id' => fn (array $attributes): int => VolunteerAssignment::query()->whereKey($attributes['volunteer_assignment_id'])->valueOrFail('party_id'),
            'minutes' => 240,
            'occurred_at' => now(),
        ];
    }
}
