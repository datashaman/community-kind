<?php

namespace Database\Factories;

use App\Enums\IntakeStatus;
use App\Models\IntakeRequest;
use App\Models\IntakeTransition;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntakeTransition>
 */
class IntakeTransitionFactory extends Factory
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
            'from_status' => null,
            'to_status' => IntakeStatus::Draft,
            'effective_at' => now(),
            'recorded_at' => now(),
            'version' => 1,
        ];
    }
}
