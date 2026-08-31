<?php

namespace App\Models;

use Database\Factories\BillingContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/** @property string $id
 * @property string $billing_account_id
 * @property string $name
 * @property string $email
 * @property list<string> $purposes
 * @property Carbon|null $removed_at
 */
#[Fillable(['billing_account_id', 'name', 'email', 'purposes', 'created_by_user_id', 'removed_at', 'removed_by_user_id'])]
class BillingContact extends Model
{
    /** @use HasFactory<BillingContactFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (BillingContact $contact): void {
            if ($contact->isDirty(['billing_account_id', 'name', 'email', 'purposes', 'created_by_user_id'])) {
                throw new LogicException('Billing Contact history is immutable; remove and replace it.');
            }
        });
        static::deleting(fn () => throw new LogicException('Billing Contact history is retained.'));
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    protected function casts(): array
    {
        return ['purposes' => 'array', 'removed_at' => 'immutable_datetime'];
    }
}
