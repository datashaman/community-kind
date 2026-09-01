<?php

namespace Database\Factories;

use App\Enums\ProgramIntakeFieldType;
use App\Models\Program;
use App\Models\ProgramIntakeField;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProgramIntakeField>
 */
class ProgramIntakeFieldFactory extends Factory
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
            'field_type' => fake()->randomElement(ProgramIntakeFieldType::cases()),
            'is_required' => false,
            'position' => fake()->numberBetween(0, 10),
            'retired_at' => null,
        ];
    }
}
