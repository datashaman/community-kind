<?php

namespace Database\Factories;

use App\Models\RestrictedAccessGrant;
use App\Models\RestrictedAccessRevocation;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestrictedAccessRevocation>
 */
class RestrictedAccessRevocationFactory extends Factory
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
            'restricted_access_grant_id' => RestrictedAccessGrant::factory(),
            'reason' => fake()->sentence(),
            'revoked_at' => now(),
        ];
    }
}
