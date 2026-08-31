<?php

namespace App\Actions\Billing;

use App\Enums\BillingAccountStatus;
use App\Models\BillingAccount;
use App\Models\BillingInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AcceptBillingInvitation
{
    public function __construct(private readonly RecordBillingAccountEvent $recordEvent) {}

    public function handle(BillingInvitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user): void {
            $locked = BillingInvitation::query()->with('billingAccount')->lockForUpdate()->findOrFail($invitation->id);
            $lockedAccount = BillingAccount::query()->lockForUpdate()->findOrFail($locked->billing_account_id);
            if (! $user->hasVerifiedEmail() || ! $locked->isPending() || mb_strtolower($user->email) !== $locked->email || $lockedAccount->status !== BillingAccountStatus::Open) {
                throw ValidationException::withMessages(['invitation' => 'This Billing Account invitation is invalid or unavailable.']);
            }
            if ($lockedAccount->memberships()->where('user_id', $user->id)->whereNull('ended_at')->exists()) {
                throw ValidationException::withMessages(['invitation' => 'You already belong to this Billing Account.']);
            }
            $lockedAccount->memberships()->create(['user_id' => $user->id, 'role' => $locked->role, 'is_owner' => $locked->offers_ownership, 'accepted_at' => now(), 'active_marker' => true]);
            $locked->update(['accepted_at' => now(), 'accepted_by_user_id' => $user->id]);
            $this->recordEvent->handle($lockedAccount, 'invitation_accepted', ['invitation_id' => $locked->id, 'user_id' => $user->id], $user);
        });
    }
}
