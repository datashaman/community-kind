<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationLifecycleEventType;
use App\Models\OrganisationAccessHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReleaseOrganisationAccessHold
{
    public function __construct(
        private InvalidateOrganisationAccess $invalidateOrganisationAccess,
        private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
    ) {}

    public function handle(OrganisationAccessHold $hold, string $releasedBy, string $reason, ?User $releasedByUser = null): OrganisationAccessHold
    {
        return DB::transaction(function () use ($hold, $releasedBy, $reason, $releasedByUser): OrganisationAccessHold {
            $hold = OrganisationAccessHold::lockForUpdate()->findOrFail($hold->id);

            if ($hold->released_at !== null) {
                return $hold;
            }

            $hold->update([
                'released_at' => now(),
                'released_by_user_id' => $releasedByUser?->id,
                'released_by' => $releasedBy,
                'release_reason' => $reason,
            ]);

            $organisation = $hold->organisation;
            $this->invalidateOrganisationAccess->handle($organisation);
            $this->recordOrganisationLifecycleEvent->handle(
                $organisation,
                OrganisationLifecycleEventType::AccessHoldReleased,
                $releasedByUser,
                metadata: [
                    'access_hold_id' => $hold->id,
                    'released_by' => $releasedBy,
                    'reason' => $reason,
                ],
            );

            return $hold;
        });
    }
}
