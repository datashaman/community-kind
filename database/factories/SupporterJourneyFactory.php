<?php

namespace Database\Factories;

use App\Enums\SupporterJourneyKind;
use App\Enums\SupporterJourneyStatus;
use App\Models\AudienceSegment;
use App\Models\SupporterJourney;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupporterJourney>
 */
class SupporterJourneyFactory extends Factory
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
            'audience_segment_id' => AudienceSegment::factory(),
            'name' => fake()->unique()->words(3, true),
            'journey_kind' => SupporterJourneyKind::General,
            'channel' => 'email',
            'subject' => 'Thank you, {{ supporter_name }}',
            'body' => 'Your {{ donation_count }} contribution(s) make a difference.',
            'status' => SupporterJourneyStatus::Draft,
            'version' => 1,
        ];
    }
}
