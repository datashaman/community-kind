<?php

namespace Database\Factories;

use App\Enums\SandboxPairStatus;
use App\Models\SandboxPair;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SandboxPair>
 */
class SandboxPairFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => SandboxPairStatus::Ready,
            'generation' => 1,
            'expires_at' => now()->addHours(12),
        ];
    }
}
