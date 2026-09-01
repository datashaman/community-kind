<?php

namespace Database\Factories;

use App\Models\PublishedImpactSnapshot;
use App\OrganisationContext;
use App\Reporting\MetricRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PublishedImpactSnapshot>
 */
class PublishedImpactSnapshotFactory extends Factory
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
            'audience' => 'board',
            'registry_version' => MetricRegistry::VERSION,
            'metrics' => [],
            'cohort_comparisons' => [],
            'period' => ['start' => now()->startOfMonth()->toAtomString(), 'endExclusive' => now()->addDay()->startOfDay()->toAtomString()],
            'filters' => [],
            'approved_at' => now(),
        ];
    }
}
