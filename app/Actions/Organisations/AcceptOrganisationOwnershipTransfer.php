<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationOwnershipTransferStatus;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\OrganisationOwnershipTransfer;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptOrganisationOwnershipTransfer
{
    public function __construct(
        private InvalidateOrganisationAccess $invalidateOrganisationAccess,
        private RecordOrganisationLifecycleEvent $recordOrganisationLifecycleEvent,
    ) {}

    public function handle(OrganisationOwnershipTransfer $transfer, User $nominee): OrganisationOwnershipTransfer
    {
        return DB::transaction(function () use ($transfer, $nominee): OrganisationOwnershipTransfer {
            $organisation = Organisation::lockForUpdate()->findOrFail($transfer->organisation_id);
            $transfer = $organisation->ownershipTransfers()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->nominee_user_id !== $nominee->id || ! $transfer->isPending()) {
                throw ValidationException::withMessages(['transfer' => __('This ownership transfer is invalid or unavailable.')]);
            }

            $nominatorMembership = Membership::where('organisation_id', $organisation->id)
                ->where('user_id', $transfer->nominated_by_user_id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();
            $nomineeMembership = Membership::where('organisation_id', $organisation->id)
                ->where('user_id', $nominee->id)
                ->whereNull('ended_at')
                ->lockForUpdate()
                ->first();

            $membershipOnHold = app(OrganisationContext::class)->run(
                $organisation,
                fn (): bool => $nominatorMembership?->isHeld() === true || $nomineeMembership?->isHeld() === true,
            );

            if (! $nominatorMembership?->is_owner || $nomineeMembership === null || $membershipOnHold) {
                throw ValidationException::withMessages(['transfer' => __('This ownership transfer is no longer valid.')]);
            }

            $nomineeMembership->update(['is_owner' => true]);
            $nominatorMembership->update(['is_owner' => false]);
            $transfer->update([
                'status' => OrganisationOwnershipTransferStatus::Accepted,
                'accepted_at' => now(),
            ]);

            $this->invalidateOrganisationAccess->handle($organisation);
            $this->recordOrganisationLifecycleEvent->handle(
                $organisation,
                OrganisationLifecycleEventType::OwnershipTransferAccepted,
                $nominee,
                metadata: [
                    'transfer_id' => $transfer->id,
                    'previous_owner_user_id' => $transfer->nominated_by_user_id,
                    'new_owner_user_id' => $nominee->id,
                ],
            );

            return $transfer;
        });
    }
}
