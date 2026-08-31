<?php

namespace Database\Factories;

use App\Enums\CaseMetricCode;
use App\Models\MetricEvent;
use App\Models\Program;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MetricEvent> */
class MetricEventFactory extends Factory
{
    public function definition(): array
    {
        return ['organisation_id' => app(OrganisationContext::class)->id(), 'program_id' => Program::factory()->state(['organisation_id' => app(OrganisationContext::class)->id()]), 'code' => CaseMetricCode::ServiceDelivered, 'value' => 1, 'dimensions' => [], 'deduplication_key' => hash('sha256', Str::uuid()->toString()), 'occurred_at' => now(), 'recorded_at' => now()];
    }
}
