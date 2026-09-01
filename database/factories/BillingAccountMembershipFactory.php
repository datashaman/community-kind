<?php

namespace Database\Factories;

use App\Enums\BillingAccountRole;
use App\Models\BillingAccount;
use App\Models\BillingAccountMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingAccountMembership>
 */
class BillingAccountMembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['billing_account_id' => BillingAccount::factory(), 'user_id' => User::factory(), 'role' => BillingAccountRole::Viewer, 'is_owner' => false, 'accepted_at' => now(), 'active_marker' => true];
    }
}
