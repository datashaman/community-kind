<?php

namespace Database\Factories;

use App\Enums\IncidentReasonCode;
use App\Enums\InstallationCapability;
use App\Models\InstallationControl;
use App\Models\SecurityIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstallationControl>
 */
class InstallationControlFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_uuid' => SecurityIncident::factory(),
            'capability' => InstallationCapability::Writes,
            'reason_code' => IncidentReasonCode::SyntheticExercise->value,
            'activated_at' => now(),
        ];
    }
}
