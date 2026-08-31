<?php

namespace App\Actions\Billing;

use App\Enums\BillingAccountStatus;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CloseBillingAccount
{
    public function __construct(private readonly RecordBillingAccountEvent $recordEvent) {}

    public function handle(BillingAccount $account, User $actor): void
    {
        DB::transaction(function () use ($account, $actor): void {
            $locked = BillingAccount::query()->lockForUpdate()->findOrFail($account->id);
            $owner = $locked->memberships()->where('user_id', $actor->id)->where('is_owner', true)->whereNull('ended_at')->exists();
            if (! $owner || $locked->status !== BillingAccountStatus::Open) {
                throw ValidationException::withMessages(['account' => 'Only an accepted Owner can close an open Billing Account.']);
            }
            if ($locked->subscriptions()->where('status', '!=', SubscriptionStatus::Ended)->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->exists()) {
                throw ValidationException::withMessages(['account' => 'End or replace every current Subscription before closing this Billing Account.']);
            }
            $locked->update(['status' => BillingAccountStatus::Closed, 'closed_at' => now(), 'closed_by_user_id' => $actor->id]);
            $this->recordEvent->handle($locked, 'account_closed', ['billing_account_id' => $locked->id], $actor);
        });
    }
}
