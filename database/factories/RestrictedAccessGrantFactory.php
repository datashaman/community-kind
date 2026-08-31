<?php

namespace Database\Factories;

use App\Enums\RestrictedAccessPermission;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestrictedAccessGrant>
 */
class RestrictedAccessGrantFactory extends Factory
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
            'membership_id' => fn (): int => app(OrganisationContext::class)->organisation()
                ->memberships()->create(['user_id' => User::factory()->create()->id])->id,
            'program_id' => fn (array $attributes): int => ServiceCase::query()
                ->whereKey($attributes['service_case_id'])->firstOrFail()->program_id,
            'service_case_id' => ServiceCase::factory(),
            'permission' => RestrictedAccessPermission::SensitiveData,
            'reason' => fake()->sentence(),
            'granted_at' => now(),
        ];
    }
}
