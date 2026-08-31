<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\PartyKind;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property PartyKind $kind
 * @property string $display_name
 * @property-read Organisation $organisation
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, PartyContactPoint> $contactPoints
 */
#[Fillable(['organisation_id', 'kind', 'display_name'])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids, SoftDeletes;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'person_party_id');
    }

    /** @return HasMany<PartyContactPoint, $this> */
    public function contactPoints(): HasMany
    {
        return $this->hasMany(PartyContactPoint::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => PartyKind::class];
    }
}
