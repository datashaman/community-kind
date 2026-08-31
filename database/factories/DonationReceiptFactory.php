<?php

namespace Database\Factories;

use App\Models\DonationPayment;
use App\Models\DonationReceipt;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DonationReceipt>
 */
class DonationReceiptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'donation_payment_id' => DonationPayment::factory(),
            'donation_id' => fn (array $attributes): string => DonationPayment::query()->whereKey($attributes['donation_payment_id'])->firstOrFail()->donation_id,
            'receipt_number' => 'DEMO-'.strtoupper(Str::random(12)),
            'amount_minor' => fn (array $attributes): int => DonationPayment::query()->whereKey($attributes['donation_payment_id'])->firstOrFail()->amount_minor,
            'currency' => fn (array $attributes): string => DonationPayment::query()->whereKey($attributes['donation_payment_id'])->firstOrFail()->currency,
            'marker' => 'Demo—Not a tax receipt',
            'issued_at' => now(),
        ];
    }
}
