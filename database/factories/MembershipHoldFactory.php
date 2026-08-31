<?php

namespace Database\Factories;

use App\Models\MembershipHold;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipHold>
 */
class MembershipHoldFactory extends Factory
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
            'membership_id' => function (array $attributes): int {
                $organisation = Organisation::query()->whereKey((int) $attributes['organisation_id'])->firstOrFail();

                return $organisation->memberships()->create([
                    'user_id' => User::factory()->create()->id,
                ])->id;
            },
            'reason' => fake()->sentence(),
            'starts_at' => now(),
            'review_at' => now()->addWeek(),
            'expires_at' => now()->addMonth(),
            'issued_by' => User::factory(),
        ];
    }
}
