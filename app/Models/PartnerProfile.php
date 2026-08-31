<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\PartnerProfileStatus;
use Database\Factories\PartnerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $party_id
 * @property string $partner_type
 * @property string $relationship_summary
 * @property PartnerProfileStatus $status
 * @property-read Party $party
 */
#[Fillable(['organisation_id', 'party_id', 'partner_type', 'status', 'relationship_summary', 'engaged_at', 'created_by_user_id'])]
class PartnerProfile extends Model
{
    /** @use HasFactory<PartnerProfileFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<PartnerCommitment, $this> */
    public function commitments(): HasMany
    {
        return $this->hasMany(PartnerCommitment::class);
    }

    protected function casts(): array
    {
        return ['status' => PartnerProfileStatus::class, 'engaged_at' => 'datetime'];
    }
}
