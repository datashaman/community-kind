<?php

namespace Database\Factories;

use App\Models\Party;
use App\Models\PartyInterest;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PartyInterest>
 */
class PartyInterestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->word().' '.fake()->word();

        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]),
            'slug' => Str::slug($label),
            'label' => $label,
        ];
    }
}
