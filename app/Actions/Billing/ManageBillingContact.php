<?php

namespace App\Actions\Billing;

use App\Enums\BillingAccountRole;
use App\Enums\BillingAccountStatus;
use App\Models\BillingAccount;
use App\Models\BillingAccountMembership;
use App\Models\BillingContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageBillingContact
{
    public function __construct(private readonly RecordBillingAccountEvent $recordEvent) {}

    /** @param list<string> $purposes */
    public function create(BillingAccount $account, User $actor, string $name, string $email, array $purposes): BillingContact
    {
        return DB::transaction(function () use ($account, $actor, $email, $name, $purposes): BillingContact {
            $lockedAccount = BillingAccount::query()->lockForUpdate()->findOrFail($account->id);
            $this->authorize($lockedAccount, $actor);
            $contact = $lockedAccount->contacts()->create(['name' => $name, 'email' => mb_strtolower($email), 'purposes' => $purposes, 'created_by_user_id' => $actor->id]);
            $this->recordEvent->handle($lockedAccount, 'contact_created', ['contact_id' => $contact->id, 'purposes' => $purposes], $actor);

            return $contact;
        });
    }

    public function remove(BillingContact $contact, User $actor): void
    {
        DB::transaction(function () use ($actor, $contact): void {
            $lockedAccount = BillingAccount::query()->lockForUpdate()->findOrFail($contact->billing_account_id);
            $lockedContact = BillingContact::query()->lockForUpdate()->findOrFail($contact->id);
            $this->authorize($lockedAccount, $actor);
            $lockedContact->update(['removed_at' => now(), 'removed_by_user_id' => $actor->id]);
            $this->recordEvent->handle($lockedAccount, 'contact_removed', ['contact_id' => $lockedContact->id], $actor);
        });
    }

    private function authorize(BillingAccount $account, User $actor): void
    {
        $membership = BillingAccountMembership::query()->where('billing_account_id', $account->id)->where('user_id', $actor->id)->whereNull('ended_at')->first();
        if ($account->status !== BillingAccountStatus::Open || $membership?->role !== BillingAccountRole::Administrator) {
            throw ValidationException::withMessages(['contact' => 'Only a Billing Administrator can manage contacts on an open account.']);
        }
    }
}
