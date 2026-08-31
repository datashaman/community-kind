<?php

namespace Database\Factories;

use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerOnboardingStatus;
use App\Models\Party;
use App\Models\SupporterRegistration;
use App\Models\VolunteerApplication;
use App\Models\VolunteerOpportunity;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VolunteerApplication>
 */
class VolunteerApplicationFactory extends Factory
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
            'volunteer_opportunity_id' => VolunteerOpportunity::factory(),
            'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]),
            'supporter_registration_id' => fn (array $attributes) => SupporterRegistration::query()->create(['organisation_id' => app(OrganisationContext::class)->id(), 'party_id' => $attributes['party_id'], 'kind' => SupporterRegistrationKind::Volunteer, 'title' => 'Volunteer registration', 'status' => SupporterRegistrationStatus::Pending, 'version' => 1])->id,
            'status' => VolunteerApplicationStatus::Submitted,
            'onboarding_status' => VolunteerOnboardingStatus::NotStarted,
            'interests' => ['community'],
            'availability' => ['weekend'],
            'follow_up_status' => 'eligible',
            'version' => 1,
            'submitted_at' => now(),
        ];
    }
}
