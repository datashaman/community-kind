<?php

namespace Database\Factories;

use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Models\Party;
use App\Models\SupporterRegistration;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupporterRegistration>
 */
class SupporterRegistrationFactory extends Factory
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
            'party_id' => fn (array $attributes): int => Party::factory()->create([
                'organisation_id' => $attributes['organisation_id'],
            ])->id,
            'kind' => SupporterRegistrationKind::Volunteer,
            'title' => fake()->sentence(3),
            'status' => SupporterRegistrationStatus::Confirmed,
            'version' => 1,
            'starts_at' => now()->addWeek(),
        ];
    }
}
