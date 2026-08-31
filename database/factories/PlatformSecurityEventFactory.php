<?php

namespace Database\Factories;

use App\Data\Auditing\VersionedAuditPayload;
use App\Enums\PlatformSecurityEventType;
use App\Models\PlatformSecurityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformSecurityEvent>
 */
class PlatformSecurityEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => PlatformSecurityEventType::OtherBrowserSessionsRevoked,
            'schema_version' => VersionedAuditPayload::CURRENT_VERSION,
            'actor_user_id' => User::factory(),
            'subject_user_id' => User::factory(),
            'metadata' => ['revoked_count' => 1],
            'occurred_at' => now(),
        ];
    }
}
