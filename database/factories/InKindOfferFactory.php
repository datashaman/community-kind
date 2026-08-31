<?php

namespace Database\Factories;

use App\Enums\InKindOfferStatus;
use App\Models\InKindOffer;
use App\Models\Party;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InKindOffer>
 */
class InKindOfferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]), 'category' => 'Food supplies', 'description' => fake()->sentence(), 'quantity' => 10, 'unit' => 'boxes', 'estimated_value_minor' => 10000, 'currency' => 'ZAR', 'condition' => 'new', 'status' => InKindOfferStatus::Offered, 'version' => 1, 'offered_at' => now()];
    }
}
