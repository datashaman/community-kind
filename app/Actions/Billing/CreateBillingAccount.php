<?php

namespace App\Actions\Billing;

use App\Enums\BillingAccountPayerKind;
use App\Enums\BillingAccountRole;
use App\Enums\BillingAccountStatus;
use App\Models\BillingAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateBillingAccount
{
    public function __construct(private readonly RecordBillingAccountEvent $recordEvent) {}

    public function handle(User $creator, BillingAccountPayerKind $kind, string $legalName): BillingAccount
    {
        if (! $creator->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['account' => 'Verify your email before creating a Billing Account.']);
        }

        return DB::transaction(function () use ($creator, $kind, $legalName): BillingAccount {
            $account = BillingAccount::query()->create(['payer_kind' => $kind, 'legal_name' => $legalName, 'status' => BillingAccountStatus::Open, 'created_by_user_id' => $creator->id]);
            $account->memberships()->create(['user_id' => $creator->id, 'role' => BillingAccountRole::Administrator, 'is_owner' => true, 'accepted_at' => now(), 'active_marker' => true]);
            $this->recordEvent->handle($account, 'account_created', ['payer_kind' => $kind->value, 'legal_name' => $legalName], $creator);

            return $account;
        });
    }
}
