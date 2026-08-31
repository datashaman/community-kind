<?php

namespace Database\Factories;

use App\Enums\BillingAccountRole;
use App\Models\BillingAccount;
use App\Models\BillingInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingInvitation>
 */
class BillingInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['billing_account_id' => BillingAccount::factory(), 'token_hash' => hash('sha256', Str::random(64)), 'email' => fake()->unique()->safeEmail(), 'role' => BillingAccountRole::Viewer, 'offers_ownership' => false, 'expires_at' => now()->addDay()];
    }
}
