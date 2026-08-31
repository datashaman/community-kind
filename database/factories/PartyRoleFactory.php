<?php

namespace Database\Factories;

use App\Enums\PartyBusinessRole;
use App\Models\Party;
use App\Models\PartyRole;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyRole>
 */
class PartyRoleFactory extends Factory
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
            'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]),
            'role' => PartyBusinessRole::Client,
        ];
    }
}
