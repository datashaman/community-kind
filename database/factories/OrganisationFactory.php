<?php

namespace Database\Factories;

use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organisation>
 */
class OrganisationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => OrganisationStatus::Pending,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganisationStatus::Active,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganisationStatus::Archived,
        ]);
    }

    public function scheduledForDeletion(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrganisationStatus::ScheduledForDeletion,
            'deletion_scheduled_for' => now()->addDays(30),
        ]);
    }

    /**
     * Indicate that the organisation has been deleted.
     */
    public function trashed(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now(),
        ]);
    }
}
