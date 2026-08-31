<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\PartyKind;
use Database\Factories\PortalAccessGrantFactory;
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
 * @property int $user_id
 * @property int $person_party_id
 * @property string $token_hash
 * @property int $access_version
 * @property Carbon $token_expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $token_used_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by_user_id
 * @property int|null $revoked_by_user_id
 * @property-read Organisation $organisation
 * @property-read User $user
 * @property-read Party $personParty
 */
#[Fillable(['organisation_id', 'user_id', 'person_party_id', 'token_hash', 'access_version', 'token_expires_at', 'verified_at', 'token_used_at', 'revoked_at', 'created_by_user_id', 'revoked_by_user_id'])]
class PortalAccessGrant extends Model
{
    /** @use HasFactory<PortalAccessGrantFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    protected static function booted(): void
    {
        static::updating(function (PortalAccessGrant $grant): void {
            if ($grant->isDirty(['organisation_id', 'user_id', 'person_party_id', 'token_hash', 'created_by_user_id'])) {
                throw new LogicException('Portal Access Grant identity is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Portal Access Grant history is immutable.'));
    }

    public function hasActiveAccess(): bool
    {
        return $this->verified_at !== null
            && $this->token_used_at !== null
            && $this->revoked_at === null
            && $this->user->hasVerifiedEmail()
            && $this->personParty->kind === PartyKind::Person
            && ! $this->personParty->trashed();
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function personParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'person_party_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'access_version' => 'integer',
            'token_expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'token_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
