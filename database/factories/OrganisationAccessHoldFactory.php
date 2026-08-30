<?php

namespace Database\Factories;

use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationAccessHold>
 */
class OrganisationAccessHoldFactory extends Factory
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
            'issuer_user_id' => User::factory(),
            'issuer' => fake()->name(),
            'reason' => fake()->sentence(),
            'scope' => OrganisationAccessScope::All,
            'access_level' => OrganisationAccessLevel::ReadOnly,
            'starts_at' => now(),
            'review_at' => now()->addDay(),
            'expires_at' => now()->addDays(7),
        ];
    }
}
