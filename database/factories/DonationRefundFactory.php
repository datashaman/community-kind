<?php

namespace Database\Factories;

use App\Models\DonationPayment;
use App\Models\DonationRefund;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DonationRefund>
 */
class DonationRefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'donation_payment_id' => DonationPayment::factory(),
            'amount_minor' => 100,
            'currency' => fn (array $attributes): string => DonationPayment::query()->whereKey($attributes['donation_payment_id'])->firstOrFail()->currency,
            'idempotency_key' => Str::uuid()->toString(),
            'provider_refund_id' => 'sim_refund_'.Str::uuid(),
            'occurred_at' => now(),
        ];
    }
}
