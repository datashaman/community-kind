<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\InKindOfferStatus;
use Database\Factories\InKindOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $party_id
 * @property string $category
 * @property string $description
 * @property string $quantity
 * @property string $unit
 * @property int|null $estimated_value_minor
 * @property string|null $currency
 * @property string $condition
 * @property InKindOfferStatus $status
 * @property string|null $fulfilment_outcome
 * @property int $version
 * @property Carbon|null $fulfilled_at
 * @property-read Party $party
 */
#[Fillable(['organisation_id', 'party_id', 'category', 'description', 'quantity', 'unit', 'estimated_value_minor', 'currency', 'condition', 'status', 'fulfilment_outcome', 'version', 'offered_at', 'fulfilled_at', 'transitioned_by_user_id'])]
class InKindOffer extends Model
{
    /** @use HasFactory<InKindOfferFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    protected function casts(): array
    {
        return ['status' => InKindOfferStatus::class, 'quantity' => 'decimal:2', 'estimated_value_minor' => 'integer', 'version' => 'integer', 'offered_at' => 'datetime', 'fulfilled_at' => 'datetime'];
    }
}
