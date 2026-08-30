<?php

namespace Database\Factories;

use App\Models\Organisation;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'organisation_id' => Organisation::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
        ];
    }
}
