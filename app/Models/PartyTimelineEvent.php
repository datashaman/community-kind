<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\PartyTimelineEventType;
use Database\Factories\PartyTimelineEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property PartyTimelineEventType $type
 * @property string $summary
 * @property Carbon $occurred_at
 */
#[Fillable(['organisation_id', 'party_id', 'type', 'subject_type', 'subject_id', 'summary', 'metadata', 'occurred_at', 'recorded_by_user_id'])]
class PartyTimelineEvent extends Model
{
    /** @use HasFactory<PartyTimelineEventFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Party timeline events are append-only.'));
        static::deleting(fn () => throw new LogicException('Party timeline events are append-only.'));
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PartyTimelineEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
