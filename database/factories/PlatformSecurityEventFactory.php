<?php

namespace Database\Factories;

use App\Models\PlatformSecurityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSecurityEvent>
 */
class PlatformSecurityEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(['other_browser_sessions_revoked']),
            'actor_user_id' => User::factory(),
            'subject_user_id' => User::factory(),
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
