<?php

namespace Database\Factories;

use App\Enums\BillingAccountPayerKind;
use App\Enums\BillingAccountStatus;
use App\Models\BillingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingAccount>
 */
class BillingAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['payer_kind' => BillingAccountPayerKind::Organisation, 'legal_name' => fake()->company(), 'status' => BillingAccountStatus::Open];
    }
}
