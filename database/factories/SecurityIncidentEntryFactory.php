<?php

namespace Database\Factories;

use App\Enums\SecurityIncidentEntryType;
use App\Models\SecurityIncident;
use App\Models\SecurityIncidentEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SecurityIncidentEntry>
 */
class SecurityIncidentEntryFactory extends Factory
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
            'type' => SecurityIncidentEntryType::Action,
            'summary' => 'Synthetic response action.',
            'status' => 'completed',
            'occurred_at' => now(),
        ];
    }
}
