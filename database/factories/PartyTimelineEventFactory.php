<?php

namespace Database\Factories;

use App\Enums\PartyTimelineEventType;
use App\Models\Party;
use App\Models\PartyTimelineEvent;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyTimelineEvent>
 */
class PartyTimelineEventFactory extends Factory
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
            'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]),
            'type' => PartyTimelineEventType::ProfileUpdated,
            'summary' => 'Profile updated',
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
