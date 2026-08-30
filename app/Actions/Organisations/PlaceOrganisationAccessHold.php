<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationLifecycleEventType;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PlaceOrganisationAccessHold
{
    public function __construct(
        private InvalidateOrganisationAccess $invalidateOrganisationAccess,
        private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
    ) {}

    public function handle(
        Organisation $organisation,
        string $issuer,
        string $reason,
        OrganisationAccessScope $scope,
        OrganisationAccessLevel $accessLevel,
        Carbon $reviewAt,
        ?Carbon $expiresAt = null,
        ?User $issuerUser = null,
        ?string $incidentUuid = null,
    ): OrganisationAccessHold {
        if ($accessLevel === OrganisationAccessLevel::Full || $reviewAt->isPast() || $expiresAt?->isPast()) {
            throw new InvalidArgumentException('Access holds must restrict access and use future review and expiry times.');
        }

        return DB::transaction(function () use ($organisation, $issuer, $reason, $scope, $accessLevel, $reviewAt, $expiresAt, $issuerUser, $incidentUuid): OrganisationAccessHold {
            $hold = $organisation->accessHolds()->create([
                'issuer_user_id' => $issuerUser?->id,
                'issuer' => $issuer,
                'reason' => $reason,
                'scope' => $scope,
                'access_level' => $accessLevel,
                'incident_uuid' => $incidentUuid,
                'starts_at' => now(),
                'review_at' => $reviewAt,
                'expires_at' => $expiresAt,
            ]);

            $this->invalidateOrganisationAccess->handle($organisation);
            $this->recordOrganisationLifecycleEvent->handle(
                $organisation,
                OrganisationLifecycleEventType::AccessHoldPlaced,
                $issuerUser,
                metadata: [
                    'access_hold_id' => $hold->id,
                    'issuer' => $issuer,
                    'reason' => $reason,
                    'scope' => $scope->value,
                    'access_level' => $accessLevel->value,
                    'incident_uuid' => $incidentUuid,
                    'review_at' => $reviewAt->toISOString(),
                    'expires_at' => $expiresAt?->toISOString(),
                ],
            );

            return $hold;
        });
    }
}
