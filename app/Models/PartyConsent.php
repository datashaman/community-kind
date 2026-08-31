<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use Database\Factories\PartyConsentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property ConsentPurpose $purpose
 * @property ConsentChannel $channel
 * @property ConsentDecision $decision
 * @property string $wording_version
 * @property string $wording
 * @property string $source
 * @property Carbon $occurred_at
 * @property string|null $supersedes_id
 */
#[Fillable(['organisation_id', 'party_id', 'purpose', 'channel', 'decision', 'wording_version', 'wording', 'source', 'occurred_at', 'supersedes_id', 'recorded_by_user_id'])]
class PartyConsent extends Model
{
    /** @use HasFactory<PartyConsentFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Party consent history is append-only.'));
        static::deleting(fn () => throw new LogicException('Party consent history is append-only.'));
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<PartyConsent, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
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
            'purpose' => ConsentPurpose::class,
            'channel' => ConsentChannel::class,
            'decision' => ConsentDecision::class,
            'occurred_at' => 'datetime',
        ];
    }
}
