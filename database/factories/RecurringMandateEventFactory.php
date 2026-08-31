<?php

namespace Database\Factories;

use App\Enums\RecurringMandateStatus;
use App\Models\RecurringMandate;
use App\Models\RecurringMandateEvent;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RecurringMandateEvent>
 */
class RecurringMandateEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'recurring_mandate_id' => RecurringMandate::factory(),
            'donation_payment_id' => null,
            'idempotency_key' => Str::uuid()->toString(),
            'provider_event_id' => 'sim_event_'.Str::uuid(),
            'from_status' => RecurringMandateStatus::Pending,
            'to_status' => RecurringMandateStatus::Active,
            'occurred_at' => now(),
        ];
    }
}
