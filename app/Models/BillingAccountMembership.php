<?php

namespace App\Models;

use App\Enums\BillingAccountRole;
use Database\Factories\BillingAccountMembershipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $billing_account_id
 * @property int $user_id
 * @property BillingAccountRole $role
 * @property bool $is_owner
 * @property Carbon $accepted_at
 * @property Carbon|null $ended_at
 * @property-read BillingAccount $billingAccount
 * @property-read User $user
 */
#[Fillable(['billing_account_id', 'user_id', 'role', 'is_owner', 'accepted_at', 'ended_at', 'active_marker'])]
class BillingAccountMembership extends Model
{
    /** @use HasFactory<BillingAccountMembershipFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (BillingAccountMembership $membership): void {
            if ($membership->isDirty(['billing_account_id', 'user_id', 'accepted_at'])) {
                throw new LogicException('Accepted Billing Account Membership identity is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Billing Account Membership history is retained.'));
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return ['role' => BillingAccountRole::class, 'is_owner' => 'boolean', 'accepted_at' => 'immutable_datetime', 'ended_at' => 'immutable_datetime', 'active_marker' => 'boolean'];
    }
}
