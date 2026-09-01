<?php

namespace App\Models;

use App\Enums\BillingAccountPayerKind;
use App\Enums\BillingAccountStatus;
use Database\Factories\BillingAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property BillingAccountPayerKind $payer_kind
 * @property string $legal_name
 * @property BillingAccountStatus $status
 * @property Carbon|null $closed_at
 * @property-read Collection<int, BillingAccountMembership> $memberships
 * @property-read Collection<int, BillingContact> $contacts
 * @property-read Collection<int, Subscription> $subscriptions
 */
#[Fillable(['payer_kind', 'legal_name', 'status', 'created_by_user_id', 'closed_at', 'closed_by_user_id'])]
class BillingAccount extends Model
{
    /** @use HasFactory<BillingAccountFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (BillingAccount $account): void {
            if ($account->isDirty(['id', 'payer_kind', 'created_by_user_id'])) {
                throw new LogicException('Billing Account identity is immutable.');
            }
            if ($account->getRawOriginal('status') === BillingAccountStatus::Closed->value) {
                throw new LogicException('A closed Billing Account is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Billing Account financial and audit history is retained.'));
    }

    /** @return HasMany<BillingAccountMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(BillingAccountMembership::class);
    }

    /** @return HasMany<BillingInvitation, $this> */
    public function invitations(): HasMany
    {
        return $this->hasMany(BillingInvitation::class);
    }

    /** @return HasMany<BillingContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(BillingContact::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** @return HasMany<BillingAccountEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BillingAccountEvent::class);
    }

    protected function casts(): array
    {
        return ['payer_kind' => BillingAccountPayerKind::class, 'status' => BillingAccountStatus::class, 'closed_at' => 'immutable_datetime'];
    }
}
