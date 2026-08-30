<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\OrganisationLifecycleEvent;
use App\Models\User;

class RecordOrganisationLifecycleEvent
{
    /** @param array<string, mixed> $metadata */
    public function handle(
        Organisation $organisation,
        OrganisationLifecycleEventType $type,
        ?User $actor = null,
        ?OrganisationStatus $fromStatus = null,
        ?OrganisationStatus $toStatus = null,
        array $metadata = [],
    ): OrganisationLifecycleEvent {
        return $organisation->lifecycleEvents()->create([
            'actor_user_id' => $actor?->id,
            'type' => $type,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
