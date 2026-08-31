<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\BillingAccount;
use App\Models\Organisation;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['billing_account_id' => BillingAccount::factory(), 'organisation_id' => Organisation::factory(), 'status' => SubscriptionStatus::PendingActivation, 'current_marker' => true];
    }
}
