<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\RestrictedAccessPermission;
use Database\Factories\RestrictedAccessGrantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $membership_id
 * @property int $program_id
 * @property string|null $service_case_id
 * @property RestrictedAccessPermission $permission
 * @property Carbon $granted_at
 * @property Carbon|null $expires_at
 */
class RestrictedAccessGrant extends Model
{
    /** @use HasFactory<RestrictedAccessGrantFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Restricted access grants are append-only.'));
        static::deleting(fn () => throw new LogicException('Restricted access grants are append-only.'));
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereDoesntHave('revocation')
            ->where(fn (Builder $active) => $active->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    /** @return HasOne<RestrictedAccessRevocation, $this> */
    public function revocation(): HasOne
    {
        return $this->hasOne(RestrictedAccessRevocation::class);
    }

    protected function casts(): array
    {
        return [
            'permission' => RestrictedAccessPermission::class,
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
