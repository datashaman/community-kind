<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\PartyRelationshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property-read Party $relatedParty
 */
#[Fillable(['organisation_id', 'party_id', 'related_party_id', 'type', 'started_at', 'ended_at'])]
class PartyRelationship extends Model
{
    /** @use HasFactory<PartyRelationshipFactory> */
    use BelongsToOrganisation, HasFactory;

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function relatedParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'related_party_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ended_at' => 'datetime'];
    }
}
