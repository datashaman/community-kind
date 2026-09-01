<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['billing_account_id', 'organisation_id', 'status', 'starts_at', 'ends_at', 'current_marker'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if (BillingAccount::query()->findOrFail($subscription->billing_account_id)->status->value !== 'open') {
                throw new LogicException('A closed Billing Account cannot begin future billing activity.');
            }
        });
        static::updating(function (Subscription $subscription): void {
            if ($subscription->isDirty(['billing_account_id', 'organisation_id'])) {
                throw new LogicException('Subscription payer and Organisation history is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Subscription history is retained.'));
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'current_marker' => 'boolean'];
    }
}
