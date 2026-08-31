<?php

namespace Database\Factories;

use App\Enums\DonationFrequency;
use App\Models\Donation;
use App\Models\DonationFund;
use App\Models\Party;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Donation>
 */
class DonationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'party_id' => Party::factory(),
            'fundraising_campaign_id' => null,
            'donation_fund_id' => DonationFund::factory(),
            'frequency' => DonationFrequency::OneOff,
            'amount_minor' => fake()->randomElement([2500, 5000, 10000]),
            'currency' => 'ZAR',
            'source_code' => 'demo_fixture',
            'idempotency_key' => Str::uuid()->toString(),
            'is_simulated' => true,
        ];
    }
}
