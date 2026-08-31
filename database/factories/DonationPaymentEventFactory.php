<?php

namespace Database\Factories;

use App\Enums\DonationPaymentStatus;
use App\Models\DonationPayment;
use App\Models\DonationPaymentEvent;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DonationPaymentEvent>
 */
class DonationPaymentEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'donation_payment_id' => DonationPayment::factory(),
            'idempotency_key' => Str::uuid()->toString(),
            'provider_event_id' => 'sim_event_'.Str::uuid(),
            'from_status' => DonationPaymentStatus::Created,
            'to_status' => DonationPaymentStatus::Pending,
            'occurred_at' => now(),
        ];
    }
}
