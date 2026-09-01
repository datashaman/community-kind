<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\SupporterJourneyRecipientStatus;
use Database\Factories\SupporterJourneyRecipientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $supporter_journey_id
 * @property int $party_id
 * @property SupporterJourneyRecipientStatus $status
 * @property string|null $variant
 * @property int $attempt_count
 * @property-read Party $party
 * @property-read SupporterJourney $journey
 */
#[Fillable(['organisation_id', 'supporter_journey_id', 'party_id', 'status', 'variant', 'attempt_count', 'last_attempted_at'])]
class SupporterJourneyRecipient extends Model
{
    /** @use HasFactory<SupporterJourneyRecipientFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<SupporterJourney, $this> */
    public function journey(): BelongsTo
    {
        return $this->belongsTo(SupporterJourney::class, 'supporter_journey_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<SupporterJourneyEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SupporterJourneyEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SupporterJourneyRecipientStatus::class,
            'last_attempted_at' => 'immutable_datetime',
        ];
    }
}
