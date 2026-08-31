<?php

namespace App\Actions\Billing;

use App\Enums\BillingAccountRole;
use App\Models\BillingAccountMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageBillingAccountMembership
{
    public function __construct(private readonly RecordBillingAccountEvent $recordEvent) {}

    public function update(BillingAccountMembership $membership, User $actor, BillingAccountRole $role, bool $isOwner): void
    {
        DB::transaction(function () use ($actor, $isOwner, $membership, $role): void {
            BillingAccountMembership::query()->where('billing_account_id', $membership->billing_account_id)->lockForUpdate()->get();
            $actorMembership = BillingAccountMembership::query()->where('billing_account_id', $membership->billing_account_id)->where('user_id', $actor->id)->whereNull('ended_at')->lockForUpdate()->first();
            $locked = BillingAccountMembership::query()->lockForUpdate()->findOrFail($membership->id);
            if ($actorMembership === null || ! $actorMembership->is_owner || $locked->ended_at !== null) {
                throw ValidationException::withMessages(['membership' => 'Only an accepted Billing Account Owner can change Membership authority.']);
            }
            if ($locked->is_owner && ! $isOwner && ! $this->hasOtherOwner($locked)) {
                throw ValidationException::withMessages(['membership' => 'The last Billing Account Owner cannot be removed.']);
            }
            $locked->update(['role' => $role, 'is_owner' => $isOwner]);
            $this->recordEvent->handle($locked->billingAccount, 'membership_changed', ['membership_id' => $locked->id, 'role' => $role->value, 'is_owner' => $isOwner], $actor);
        });
    }

    public function leave(BillingAccountMembership $membership, User $actor): void
    {
        DB::transaction(function () use ($actor, $membership): void {
            BillingAccountMembership::query()->where('billing_account_id', $membership->billing_account_id)->lockForUpdate()->get();
            $locked = BillingAccountMembership::query()->lockForUpdate()->findOrFail($membership->id);
            $actorMembership = BillingAccountMembership::query()->where('billing_account_id', $membership->billing_account_id)->where('user_id', $actor->id)->whereNull('ended_at')->first();
            if ($locked->ended_at !== null || ($locked->user_id !== $actor->id && $actorMembership?->is_owner !== true)) {
                throw ValidationException::withMessages(['membership' => 'Only the member or an accepted Owner can end this Membership.']);
            }
            if ($locked->is_owner && ! $this->hasOtherOwner($locked)) {
                throw ValidationException::withMessages(['membership' => 'The last Billing Account Owner cannot leave.']);
            }
            $locked->update(['ended_at' => now(), 'active_marker' => null]);
            $this->recordEvent->handle($locked->billingAccount, 'membership_ended', ['membership_id' => $locked->id], $actor);
        });
    }

    private function hasOtherOwner(BillingAccountMembership $membership): bool
    {
        return BillingAccountMembership::query()->where('billing_account_id', $membership->billing_account_id)->whereKeyNot($membership->id)->where('is_owner', true)->whereNull('ended_at')->exists();
    }
}
