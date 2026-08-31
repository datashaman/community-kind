<?php

namespace Database\Factories;

use App\Models\DonationFund;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DonationFund>
 */
class DonationFundFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word().' '.fake()->word();

        return ['organisation_id' => app(OrganisationContext::class)->id(), 'name' => ucfirst($name), 'slug' => str($name)->slug(), 'is_simulated' => true];
    }
}
