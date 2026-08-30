<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\OrganisationSlug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationSlug>
 */
class OrganisationSlugFactory extends Factory
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
            'slug' => fake()->unique()->slug(2),
            'redirect_until' => now()->addDays(30),
            'quarantined_until' => now()->addDays(120),
        ];
    }
}
