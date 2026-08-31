<?php

namespace Database\Factories;

use App\Enums\DonationPaymentStatus;
use App\Models\Donation;
use App\Models\DonationPayment;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DonationPayment>
 */
class DonationPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'donation_id' => Donation::factory(),
            'recurring_mandate_id' => null,
            'attempt_number' => 1,
            'amount_minor' => fn (array $attributes): int => Donation::query()->whereKey($attributes['donation_id'])->firstOrFail()->amount_minor,
            'currency' => fn (array $attributes): string => Donation::query()->whereKey($attributes['donation_id'])->firstOrFail()->currency,
            'status' => DonationPaymentStatus::Created,
            'version' => 1,
            'idempotency_key' => Str::uuid()->toString(),
            'provider_payment_id' => 'sim_payment_'.Str::uuid(),
        ];
    }
}
