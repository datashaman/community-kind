<?php

namespace Database\Factories;

use App\Data\Auditing\VersionedAuditPayload;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\TenantAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantAuditEvent>
 */
class TenantAuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organisation_id' => Organisation::factory(),
            'actor_user_id' => User::factory(),
            'type' => TenantAuditEventType::ProgramUpdated,
            'schema_version' => VersionedAuditPayload::CURRENT_VERSION,
            'subject_type' => 'program',
            'subject_id' => (string) fake()->numberBetween(1, 1000),
            'payload' => [
                'program_id' => fake()->numberBetween(1, 1000),
                'changed_fields' => ['name'],
            ],
            'occurred_at' => now(),
        ];
    }
}
