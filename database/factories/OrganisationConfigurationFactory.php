<?php

namespace Database\Factories;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Models\OrganisationConfiguration;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganisationConfiguration>
 */
class OrganisationConfigurationFactory extends Factory
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
            'area' => OrganisationConfigurationArea::Reporting,
            'configuration_key' => 'impact',
            'version' => 1,
            'definition' => ['public_metric_ids' => ['engagement.event_attendance'], 'pack_metric_ids' => ['engagement.event_attendance']],
            'status' => OrganisationConfigurationStatus::Active,
            'activated_at' => now(),
        ];
    }
}
