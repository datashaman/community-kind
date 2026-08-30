<?php

namespace App\Models;

use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use Database\Factories\OrganisationAccessHoldFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property OrganisationAccessScope $scope
 * @property OrganisationAccessLevel $access_level
 * @property Carbon $starts_at
 * @property Carbon $review_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $released_at
 */
#[Fillable(['organisation_id', 'issuer_user_id', 'issuer', 'reason', 'scope', 'access_level', 'incident_uuid', 'starts_at', 'review_at', 'expires_at', 'released_at', 'released_by_user_id', 'released_by', 'release_reason'])]
class OrganisationAccessHold extends Model
{
    /** @use HasFactory<OrganisationAccessHoldFactory> */
    use HasFactory, HasUlids;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function issuerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_user_id');
    }

    public function isActiveAt(Carbon $at): bool
    {
        return $this->released_at === null
            && $this->starts_at->lte($at)
            && ($this->expires_at === null || $this->expires_at->gt($at));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope' => OrganisationAccessScope::class,
            'access_level' => OrganisationAccessLevel::class,
            'starts_at' => 'datetime',
            'review_at' => 'datetime',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
