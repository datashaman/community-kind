<?php

namespace Database\Factories;

use App\Models\BillingAccount;
use App\Models\BillingContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingContact>
 */
class BillingContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['billing_account_id' => BillingAccount::factory(), 'name' => fake()->name(), 'email' => fake()->safeEmail(), 'purposes' => ['invoice']];
    }
}
