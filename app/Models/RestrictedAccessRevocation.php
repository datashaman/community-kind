<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\RestrictedAccessRevocationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property string $restricted_access_grant_id
 * @property string $reason
 * @property Carbon $revoked_at
 */
class RestrictedAccessRevocation extends Model
{
    /** @use HasFactory<RestrictedAccessRevocationFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Restricted access revocations are append-only.'));
        static::deleting(fn () => throw new LogicException('Restricted access revocations are append-only.'));
    }

    /** @return BelongsTo<RestrictedAccessGrant, $this> */
    public function grant(): BelongsTo
    {
        return $this->belongsTo(RestrictedAccessGrant::class, 'restricted_access_grant_id');
    }

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }
}
