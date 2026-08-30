<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationOwnershipTransferStatus;
use App\Models\Organisation;
use App\Models\OrganisationOwnershipTransfer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NominateOrganisationOwnershipTransfer
{
    public function __construct(private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent) {}

    public function handle(Organisation $organisation, User $owner, User $nominee): OrganisationOwnershipTransfer
    {
        return DB::transaction(function () use ($organisation, $owner, $nominee): OrganisationOwnershipTransfer {
            $organisation = Organisation::lockForUpdate()->findOrFail($organisation->id);

            if (! $owner->ownsOrganisation($organisation) || ! $nominee->belongsToOrganisation($organisation) || $nominee->ownsOrganisation($organisation)) {
                throw ValidationException::withMessages([
                    'nominee_user_id' => __('Ownership can only be transferred by an Owner to a non-owner member.'),
                ]);
            }

            $organisation->ownershipTransfers()
                ->where('status', OrganisationOwnershipTransferStatus::Pending)
                ->where('expires_at', '<=', now())
                ->update(['status' => OrganisationOwnershipTransferStatus::Expired]);

            if ($organisation->ownershipTransfers()->where('status', OrganisationOwnershipTransferStatus::Pending)->exists()) {
                throw ValidationException::withMessages([
                    'nominee_user_id' => __('This organisation already has a pending ownership transfer.'),
                ]);
            }

            $transfer = $organisation->ownershipTransfers()->create([
                'nominated_by_user_id' => $owner->id,
                'nominee_user_id' => $nominee->id,
                'status' => OrganisationOwnershipTransferStatus::Pending,
                'expires_at' => now()->addHours((int) config('organisation_lifecycle.ownership_transfer_hours')),
            ]);

            $this->recordOrganisationLifecycleEvent->handle(
                $organisation,
                OrganisationLifecycleEventType::OwnershipTransferNominated,
                $owner,
                metadata: ['transfer_id' => $transfer->id, 'nominee_user_id' => $nominee->id],
            );

            return $transfer;
        });
    }
}
