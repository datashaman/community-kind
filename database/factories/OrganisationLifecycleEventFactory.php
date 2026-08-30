<?php

namespace Database\Factories;

use App\Enums\OrganisationLifecycleEventType;
use App\Models\Organisation;
use App\Models\OrganisationLifecycleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationLifecycleEvent>
 */
class OrganisationLifecycleEventFactory extends Factory
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
            'actor_user_id' => User::factory(),
            'type' => OrganisationLifecycleEventType::StatusChanged,
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
