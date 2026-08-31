<?php

namespace Database\Factories;

use App\Models\BillingAccount;
use App\Models\BillingAccountEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingAccountEvent>
 */
class BillingAccountEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['billing_account_id' => BillingAccount::factory(), 'type' => 'test_event', 'payload' => [], 'occurred_at' => now()];
    }
}
