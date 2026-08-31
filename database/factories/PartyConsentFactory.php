<?php

namespace Database\Factories;

use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Models\Party;
use App\Models\PartyConsent;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartyConsent>
 */
class PartyConsentFactory extends Factory
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
            'purpose' => ConsentPurpose::Service,
            'channel' => ConsentChannel::NotApplicable,
            'decision' => ConsentDecision::Granted,
            'wording_version' => 'v1',
            'wording' => 'I consent to receiving this service.',
            'source' => 'in_person',
            'occurred_at' => now(),
        ];
    }
}
