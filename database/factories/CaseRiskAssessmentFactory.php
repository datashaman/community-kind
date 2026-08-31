<?php

namespace Database\Factories;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Enums\CaseClassification;
use App\Models\CaseRiskAssessment;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CaseRiskAssessment>
 */
class CaseRiskAssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'organisation_id' => app(OrganisationContext::class)->id(),
            'service_case_id' => ServiceCase::factory(),
            'type' => 'risk_assessment',
            'classification' => CaseClassification::HighlyRestricted,
            'encrypted_content' => fake()->sentence(),
            'data_key_version' => app(ClassifiedDataEncrypter::class)->currentVersion(),
            'effective_at' => now(),
        ];
    }
}
