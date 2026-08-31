<?php

namespace Database\Factories;

use App\Enums\SecurityIncidentClassification;
use App\Enums\SecurityIncidentSeverity;
use App\Enums\SecurityIncidentStatus;
use App\Models\SecurityIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityIncident>
 */
class SecurityIncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classification' => SecurityIncidentClassification::Incident,
            'severity' => SecurityIncidentSeverity::S3Medium,
            'status' => SecurityIncidentStatus::Reported,
            'detection_source' => 'synthetic_test',
            'summary' => 'Synthetic incident for testing.',
            'detected_at' => now(),
        ];
    }
}
