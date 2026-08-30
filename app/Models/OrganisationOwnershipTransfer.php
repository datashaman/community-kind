<?php

namespace App\Models;

use App\Enums\OrganisationOwnershipTransferStatus;
use Database\Factories\OrganisationOwnershipTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $nominated_by_user_id
 * @property int $nominee_user_id
 * @property OrganisationOwnershipTransferStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $cancelled_at
 * @property-read User $nominatedBy
 * @property-read User $nominee
 */
#[Fillable(['organisation_id', 'nominated_by_user_id', 'nominee_user_id', 'status', 'expires_at', 'accepted_at', 'cancelled_at'])]
class OrganisationOwnershipTransfer extends Model
{
    /** @use HasFactory<OrganisationOwnershipTransferFactory> */
    use HasFactory, HasUlids;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function nominatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function nominee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominee_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === OrganisationOwnershipTransferStatus::Pending
            && $this->expires_at->isFuture();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => OrganisationOwnershipTransferStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
