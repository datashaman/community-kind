<?php

namespace Database\Factories;

use App\Enums\VolunteerAssignmentStatus;
use App\Models\VolunteerApplication;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerShift;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerAssignment>
 */
class VolunteerAssignmentFactory extends Factory
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
            'volunteer_application_id' => VolunteerApplication::factory(),
            'volunteer_shift_id' => fn (array $attributes): string => VolunteerShift::factory()->create([
                'volunteer_opportunity_id' => VolunteerApplication::query()->whereKey($attributes['volunteer_application_id'])->valueOrFail('volunteer_opportunity_id'),
            ])->id,
            'party_id' => fn (array $attributes): int => VolunteerApplication::query()->whereKey($attributes['volunteer_application_id'])->valueOrFail('party_id'),
            'status' => VolunteerAssignmentStatus::Confirmed,
            'version' => 1,
            'confirmed_at' => now(),
        ];
    }
}
