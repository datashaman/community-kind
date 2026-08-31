<?php

namespace Database\Factories;

use App\Enums\VolunteerCredentialStatus;
use App\Models\VolunteerApplication;
use App\Models\VolunteerCredential;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerCredential>
 */
class VolunteerCredentialFactory extends Factory
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
            'party_id' => fn (array $attributes): int => VolunteerApplication::query()->whereKey($attributes['volunteer_application_id'])->valueOrFail('party_id'),
            'type' => 'Orientation',
            'status' => VolunteerCredentialStatus::Verified,
            'verified_at' => now(),
            'expires_at' => now()->addYear(),
        ];
    }
}
