<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\Team;
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
            'team_id' => Team::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
        ];
    }
}
