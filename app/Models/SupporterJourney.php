<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\SupporterJourneyStatus;
use Database\Factories\SupporterJourneyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $audience_segment_id
 * @property string $name
 * @property string $subject
 * @property string $body
 * @property SupporterJourneyStatus $status
 * @property list<array{uuid: string, displayName: string, donationCount: int<0, max>}>|null $audience_snapshot
 * @property string|null $approval_hash
 * @property-read AudienceSegment $audienceSegment
 * @property-read int $recipients_count
 */
#[Fillable(['organisation_id', 'audience_segment_id', 'name', 'subject', 'body', 'status', 'audience_snapshot', 'approval_hash', 'approved_at', 'approved_by_user_id', 'created_by_user_id'])]
class SupporterJourney extends Model
{
    /** @use HasFactory<SupporterJourneyFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (SupporterJourney $journey): void {
            $originalStatus = $journey->getRawOriginal('status');

            if ($originalStatus !== SupporterJourneyStatus::Draft->value
                && $journey->isDirty(['audience_segment_id', 'subject', 'body', 'status', 'audience_snapshot', 'approval_hash'])) {
                throw new LogicException('Approved journey content and audience are immutable.');
            }

            if ($originalStatus === SupporterJourneyStatus::Draft->value
                && $journey->isDirty('status')
                && ($journey->status !== SupporterJourneyStatus::Approved || $journey->audience_snapshot === null || $journey->approval_hash === null)) {
                throw new LogicException('A journey can only leave draft through a complete approval.');
            }
        });
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<AudienceSegment, $this> */
    public function audienceSegment(): BelongsTo
    {
        return $this->belongsTo(AudienceSegment::class);
    }

    /** @return HasMany<SupporterJourneyRecipient, $this> */
    public function recipients(): HasMany
    {
        return $this->hasMany(SupporterJourneyRecipient::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => SupporterJourneyStatus::class,
            'audience_snapshot' => 'array',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
