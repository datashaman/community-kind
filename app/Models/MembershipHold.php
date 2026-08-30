<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\MembershipHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $membership_id
 * @property string $reason
 * @property Carbon $starts_at
 * @property Carbon $review_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $released_at
 * @property int $issued_by
 * @property int|null $released_by
 */
#[Fillable(['organisation_id', 'membership_id', 'reason', 'starts_at', 'review_at', 'expires_at', 'released_at', 'issued_by', 'released_by'])]
class MembershipHold extends Model
{
    /** @use HasFactory<MembershipHoldFactory> */
    use BelongsToOrganisation, HasFactory;

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** @return BelongsTo<User, $this> */
    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function isActive(): bool
    {
        return $this->released_at === null
            && ! $this->starts_at->isFuture()
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'review_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
