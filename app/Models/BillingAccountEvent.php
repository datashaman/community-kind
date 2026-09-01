<?php

namespace App\Models;

use Database\Factories\BillingAccountEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['billing_account_id', 'type', 'payload', 'actor_user_id', 'occurred_at'])]
class BillingAccountEvent extends Model
{
    /** @use HasFactory<BillingAccountEventFactory> */
    use HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Billing Account events are immutable.'));
        static::deleting(fn () => throw new LogicException('Billing Account events are immutable.'));
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'immutable_datetime'];
    }
}
