<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\SupporterJourneyKind;
use App\Enums\SupporterJourneyStatus;
use Carbon\CarbonImmutable;
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
 * @property SupporterJourneyKind $journey_kind
 * @property string $channel
 * @property SupporterJourneyStatus $status
 * @property int $version
 * @property array<string, string>|null $experiment
 * @property CarbonImmutable|null $scheduled_for
 * @property CarbonImmutable|null $paused_at
 * @property list<array{uuid: string, displayName: string, donationCount: int<0, max>, activityFrequency: int, activityValue: int|null}>|null $audience_snapshot
 * @property string|null $approval_hash
 * @property-read AudienceSegment $audienceSegment
 * @property-read int $recipients_count
 */
#[Fillable(['organisation_id', 'audience_segment_id', 'name', 'journey_kind', 'channel', 'subject', 'body', 'status', 'version', 'audience_snapshot', 'approval_hash', 'approved_at', 'scheduled_for', 'paused_at', 'experiment', 'approved_by_user_id', 'created_by_user_id'])]
class SupporterJourney extends Model
{
    /** @use HasFactory<SupporterJourneyFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (SupporterJourney $journey): void {
            $originalStatus = $journey->getRawOriginal('status');

            if ($originalStatus !== SupporterJourneyStatus::Draft->value
                && $journey->isDirty(['audience_segment_id', 'journey_kind', 'channel', 'subject', 'body', 'experiment', 'audience_snapshot', 'approval_hash'])) {
                throw new LogicException('Approved journey content and audience are immutable.');
            }

            if ($originalStatus === SupporterJourneyStatus::Draft->value
                && $journey->isDirty('status')
                && ($journey->status !== SupporterJourneyStatus::Approved || $journey->audience_snapshot === null || $journey->approval_hash === null)) {
                throw new LogicException('A journey can only leave draft through a complete approval.');
            }

            if ($originalStatus !== SupporterJourneyStatus::Draft->value && $journey->isDirty('status')) {
                $original = SupporterJourneyStatus::from($originalStatus);
                if (! in_array($journey->status, $original->allowedTransitions(), true)) {
                    throw new LogicException("Cannot transition supporter journey from {$original->value} to {$journey->status->value}.");
                }
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
            'journey_kind' => SupporterJourneyKind::class,
            'version' => 'integer',
            'audience_snapshot' => 'array',
            'experiment' => 'array',
            'approved_at' => 'immutable_datetime',
            'scheduled_for' => 'immutable_datetime',
            'paused_at' => 'immutable_datetime',
        ];
    }
}
