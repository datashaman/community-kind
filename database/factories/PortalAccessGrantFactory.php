<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PortalAccessGrant;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PortalAccessGrant>
 */
class PortalAccessGrantFactory extends Factory
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
            'user_id' => User::factory(),
            'person_party_id' => fn (array $attributes): int => Party::factory()->create([
                'organisation_id' => $attributes['organisation_id'],
            ])->id,
            'token_hash' => hash('sha256', Str::random(64)),
            'access_version' => 1,
            'token_expires_at' => now()->addMinutes(30),
        ];
    }
}
