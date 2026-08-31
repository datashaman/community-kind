<?php

namespace App\Actions\Auditing;

use App\Data\Auditing\VersionedAuditPayload;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\TenantAuditEvent;
use App\Models\User;

class RecordTenantAuditEvent
{
    /** @param array<string, mixed> $payload */
    public function handle(
        Organisation $organisation,
        TenantAuditEventType $type,
        string $subjectType,
        string $subjectId,
        array $payload,
        ?User $actor = null,
    ): TenantAuditEvent {
        return TenantAuditEvent::query()->create([
            'organisation_id' => $organisation->id,
            'actor_user_id' => $actor?->id,
            'type' => $type,
            'schema_version' => VersionedAuditPayload::CURRENT_VERSION,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
