<?php

namespace Database\Factories;

use App\Enums\PartnerProfileStatus;
use App\Enums\PartyKind;
use App\Models\PartnerProfile;
use App\Models\Party;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerProfile>
 */
class PartnerProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id(), 'kind' => PartyKind::Organisation]), 'partner_type' => 'local_business', 'status' => PartnerProfileStatus::Active, 'relationship_summary' => fake()->sentence(), 'engaged_at' => now()];
    }
}
