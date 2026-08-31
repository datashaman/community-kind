<?php

namespace Database\Factories;

use App\Models\AuditDigestManifest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditDigestManifest>
 */
class AuditDigestManifestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'manifest_date' => fake()->unique()->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
            'event_count' => 0,
            'event_digest' => hash('sha256', ''),
            'previous_manifest_digest' => null,
            'manifest_digest' => hash('sha256', fake()->uuid()),
            'signature' => hash('sha256', fake()->uuid()),
            'event_export_path' => 'audit/fake/events.jsonl',
            'manifest_path' => 'audit/fake/manifest.json',
            'verified_at' => null,
        ];
    }
}
