<?php

namespace Database\Factories;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\PartyBusinessRole;
use App\Models\AudienceSegment;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudienceSegment>
 */
class AudienceSegmentFactory extends Factory
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
            'name' => fake()->unique()->word().' '.fake()->word().' supporters',
            'criteria' => [
                'purpose' => ConsentPurpose::SupporterUpdates->value,
                'channel' => ConsentChannel::Email->value,
                'role' => PartyBusinessRole::Donor->value,
                'service_area' => null,
                'interest' => null,
                'donation_activity' => true,
                'campaign_source' => null,
                'activity_type' => 'donation',
                'recency_days' => null,
                'minimum_frequency' => 1,
                'minimum_value' => null,
            ],
        ];
    }
}
