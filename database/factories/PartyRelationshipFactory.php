<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyRelationship;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyRelationship>
 */
class PartyRelationshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $organisationId = app(OrganisationContext::class)->id();

        return [
            'organisation_id' => $organisationId,
            'party_id' => Party::factory()->state(['organisation_id' => $organisationId]),
            'related_party_id' => Party::factory()->state(['organisation_id' => $organisationId]),
            'type' => 'household_member',
            'started_at' => now(),
        ];
    }
}
