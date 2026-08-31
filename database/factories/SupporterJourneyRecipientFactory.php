<?php

namespace Database\Factories;

use App\Enums\SupporterJourneyRecipientStatus;
use App\Models\Party;
use App\Models\SupporterJourney;
use App\Models\SupporterJourneyRecipient;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupporterJourneyRecipient>
 */
class SupporterJourneyRecipientFactory extends Factory
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
            'supporter_journey_id' => SupporterJourney::factory(),
            'party_id' => Party::factory(),
            'status' => SupporterJourneyRecipientStatus::Queued,
        ];
    }
}
