<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\SupporterJourneyEventType;
use Database\Factories\SupporterJourneyEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $supporter_journey_recipient_id
 * @property string $idempotency_key
 * @property SupporterJourneyEventType $type
 * @property Carbon $occurred_at
 * @property-read SupporterJourneyRecipient $recipient
 */
#[Fillable(['organisation_id', 'supporter_journey_recipient_id', 'idempotency_key', 'type', 'from_status', 'to_status', 'metadata', 'occurred_at'])]
class SupporterJourneyEvent extends Model
{
    /** @use HasFactory<SupporterJourneyEventFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Journey events are append-only.'));
        static::deleting(fn () => throw new LogicException('Journey events are append-only.'));
    }

    /** @return BelongsTo<SupporterJourneyRecipient, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(SupporterJourneyRecipient::class, 'supporter_journey_recipient_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SupporterJourneyEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
