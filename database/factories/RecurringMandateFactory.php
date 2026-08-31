<?php

namespace Database\Factories;

use App\Enums\RecurringMandateStatus;
use App\Models\Donation;
use App\Models\RecurringMandate;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecurringMandate>
 */
class RecurringMandateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'donation_id' => Donation::factory(),
            'party_id' => fn (array $attributes): int => Donation::query()->whereKey($attributes['donation_id'])->firstOrFail()->party_id,
            'amount_minor' => fn (array $attributes): int => Donation::query()->whereKey($attributes['donation_id'])->firstOrFail()->amount_minor,
            'currency' => fn (array $attributes): string => Donation::query()->whereKey($attributes['donation_id'])->firstOrFail()->currency,
            'interval' => 'monthly',
            'status' => RecurringMandateStatus::Pending,
            'version' => 1,
            'provider_mandate_id' => 'sim_mandate_'.Str::uuid(),
        ];
    }
}
