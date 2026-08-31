<?php

namespace Database\Factories;

use App\Enums\SupporterJourneyEventType;
use App\Enums\SupporterJourneyRecipientStatus;
use App\Models\SupporterJourneyEvent;
use App\Models\SupporterJourneyRecipient;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SupporterJourneyEvent>
 */
class SupporterJourneyEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => app(OrganisationContext::class)->id(),
            'supporter_journey_recipient_id' => SupporterJourneyRecipient::factory(),
            'idempotency_key' => Str::uuid()->toString(),
            'type' => SupporterJourneyEventType::Queued,
            'from_status' => SupporterJourneyRecipientStatus::Queued->value,
            'to_status' => SupporterJourneyRecipientStatus::Queued->value,
            'metadata' => ['simulation' => true],
            'occurred_at' => now(),
        ];
    }
}
