<?php

namespace App\Actions\Billing;

use App\Data\IssuedBillingInvitation;
use App\Enums\BillingAccountRole;
use App\Enums\BillingAccountStatus;
use App\Models\BillingAccount;
use App\Models\BillingAccountMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class IssueBillingInvitation
{
    public function __construct(private readonly RecordBillingAccountEvent $recordEvent) {}

    public function handle(BillingAccount $account, User $actor, string $email, BillingAccountRole $role, bool $offersOwnership): IssuedBillingInvitation
    {
        return DB::transaction(function () use ($account, $actor, $email, $offersOwnership, $role): IssuedBillingInvitation {
            $lockedAccount = BillingAccount::query()->lockForUpdate()->findOrFail($account->id);
            $actorMembership = BillingAccountMembership::query()->where('billing_account_id', $lockedAccount->id)->where('user_id', $actor->id)->whereNull('ended_at')->first();
            if ($lockedAccount->status !== BillingAccountStatus::Open || $actorMembership === null || ($actorMembership->role !== BillingAccountRole::Administrator && ! $actorMembership->is_owner) || ($offersOwnership && ! $actorMembership->is_owner)) {
                throw ValidationException::withMessages(['invitation' => 'You cannot issue this Billing Account invitation.']);
            }
            if ($lockedAccount->invitations()->where('email', Str::lower($email))->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->exists()) {
                throw ValidationException::withMessages(['email' => 'A pending invitation already exists.']);
            }
            $token = Str::random(64);
            $invitation = $lockedAccount->invitations()->create(['token_hash' => hash('sha256', $token), 'email' => Str::lower($email), 'role' => $role, 'offers_ownership' => $offersOwnership, 'invited_by_user_id' => $actor->id, 'expires_at' => now()->addHours(72)]);
            $this->recordEvent->handle($lockedAccount, 'invitation_issued', ['invitation_id' => $invitation->id, 'email' => $invitation->email, 'role' => $role->value, 'offers_ownership' => $offersOwnership], $actor);

            return new IssuedBillingInvitation($invitation, $token);
        });
    }
}
