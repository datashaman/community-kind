<?php

namespace App\Actions\Security;

use App\Data\Auditing\VersionedAuditPayload;
use App\Enums\PlatformSecurityEventType;
use App\Models\PlatformSecurityEvent;
use App\Models\User;

class RecordPlatformSecurityEvent
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        PlatformSecurityEventType $type,
        array $metadata,
        ?User $actor = null,
        ?User $subject = null,
        ?string $incidentUuid = null,
    ): PlatformSecurityEvent {
        return PlatformSecurityEvent::create([
            'type' => $type,
            'schema_version' => VersionedAuditPayload::CURRENT_VERSION,
            'incident_uuid' => $incidentUuid,
            'actor_user_id' => $actor?->id,
            'subject_user_id' => $subject?->id,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
