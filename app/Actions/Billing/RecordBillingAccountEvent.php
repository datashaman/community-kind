<?php

namespace App\Actions\Billing;

use App\Models\BillingAccount;
use App\Models\BillingAccountEvent;
use App\Models\User;

final class RecordBillingAccountEvent
{
    /** @param array<string, mixed> $payload */
    public function handle(BillingAccount $account, string $type, array $payload, ?User $actor): BillingAccountEvent
    {
        return $account->events()->create(['type' => $type, 'payload' => $payload, 'actor_user_id' => $actor?->id, 'occurred_at' => now()]);
    }
}
