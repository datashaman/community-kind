<?php

namespace Database\Factories;

use App\Enums\ServiceCaseStatus;
use App\Models\IntakeRequest;
use App\Models\ServiceCase;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceCase>
 */
class ServiceCaseFactory extends Factory
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
            'intake_request_id' => IntakeRequest::factory(),
            'program_id' => fn (array $attributes): int => IntakeRequest::query()->where('id', $attributes['intake_request_id'])->firstOrFail()->program_id,
            'party_id' => fn (array $attributes): int => IntakeRequest::query()->where('id', $attributes['intake_request_id'])->firstOrFail()->party_id,
            'status' => ServiceCaseStatus::Open,
            'confidentiality' => 'confidential',
            'opened_at' => now(),
        ];
    }
}
