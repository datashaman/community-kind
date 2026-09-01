<?php

namespace Database\Factories;

use App\Models\ProgramTaxonomy;
use App\Models\ProgramTaxonomyValue;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgramTaxonomyValue>
 */
class ProgramTaxonomyValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->sentence(2);

        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'program_taxonomy_id' => ProgramTaxonomy::factory(),
            'key' => Str::snake($label),
            'label' => Str::title($label),
            'position' => fake()->numberBetween(0, 10),
            'retired_at' => null,
        ];
    }
}
