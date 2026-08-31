<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\EligibilityStatus;
use App\Enums\IntakeStatus;
use App\Enums\IntakeUrgency;
use App\Models\IntakeRequest;
use App\Models\Party;
use App\Models\Program;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntakeRequest>
 */
class IntakeRequestFactory extends Factory
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
            'program_id' => Program::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]),
            'party_id' => Party::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]),
            'type' => 'content',
            'encrypted_content' => new ClassifiedValue(json_encode([
                'narrative' => fake()->paragraph(),
                'presenting_needs' => fake()->sentence(),
                'intake_fields' => [],
            ], JSON_THROW_ON_ERROR)),
            'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(),
            'eligibility_context' => [],
            'eligibility_status' => EligibilityStatus::NeedsReview,
            'status' => IntakeStatus::Draft,
            'urgency' => IntakeUrgency::Routine,
            'risk_flags' => [],
            'version' => 1,
            'source' => 'factory',
        ];
    }
}
