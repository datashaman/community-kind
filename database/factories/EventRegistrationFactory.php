<?php

namespace Database\Factories;

use App\Enums\EventRegistrationStatus;
use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Models\CommunityEvent;
use App\Models\EventRegistration;
use App\Models\Party;
use App\Models\SupporterRegistration;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRegistration>
 */
class EventRegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'community_event_id' => CommunityEvent::factory(), 'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]), 'supporter_registration_id' => fn (array $attributes): string => SupporterRegistration::query()->create(['organisation_id' => app(OrganisationContext::class)->id(), 'party_id' => $attributes['party_id'], 'kind' => SupporterRegistrationKind::Event, 'title' => 'Community event', 'status' => SupporterRegistrationStatus::Confirmed, 'version' => 1])->id, 'status' => EventRegistrationStatus::Confirmed, 'version' => 1, 'registered_at' => now()];
    }
}
