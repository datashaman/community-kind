<?php

namespace App\Models;

use App\Enums\BillingAccountRole;
use Database\Factories\BillingInvitationFactory;
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
 * @property string $token_hash
 * @property string $email
 * @property BillingAccountRole $role
 * @property bool $offers_ownership
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property-read BillingAccount $billingAccount
 */
#[Fillable(['billing_account_id', 'token_hash', 'email', 'role', 'offers_ownership', 'invited_by_user_id', 'expires_at', 'accepted_at', 'accepted_by_user_id', 'revoked_at', 'revoked_by_user_id'])]
class BillingInvitation extends Model
{
    /** @use HasFactory<BillingInvitationFactory> */
    use HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (BillingInvitation $invitation): void {
            if ($invitation->isDirty(['billing_account_id', 'token_hash', 'email', 'role', 'offers_ownership', 'invited_by_user_id', 'expires_at'])) {
                throw new LogicException('Billing Invitation terms are immutable after issue.');
            }
        });
        static::deleting(fn () => throw new LogicException('Billing Invitation history is retained.'));
    }

    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token_hash', hash('sha256', $token))->first();
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<BillingAccount, $this> */
    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    protected function casts(): array
    {
        return ['role' => BillingAccountRole::class, 'offers_ownership' => 'boolean', 'expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }
}
