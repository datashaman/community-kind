<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\ProgramEligibilityQuestion;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgramEligibilityQuestion>
 */
class ProgramEligibilityQuestionFactory extends Factory
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
            'program_id' => Program::factory()->for(app(OrganisationContext::class)->organisation()),
            'key' => Str::snake($label),
            'label' => Str::title($label),
            'is_required' => false,
            'position' => fake()->numberBetween(0, 10),
            'retired_at' => null,
        ];
    }
}
