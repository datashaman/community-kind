<?php

namespace App\Models;

use App\Enums\OrganisationRole;
use Database\Factories\OrganisationInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $token_hash
 * @property int $organisation_id
 * @property string $email
 * @property OrganisationRole $role
 * @property int|null $person_party_id
 * @property string|null $new_person_name
 * @property bool $offers_ownership
 * @property int $invited_by
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read User $inviter
 * @property-read Party|null $personParty
 * @property-read Collection<int, OrganisationInvitationRoleAssignment> $roleAssignments
 */
#[Fillable(['token_hash', 'organisation_id', 'email', 'person_party_id', 'new_person_name', 'role', 'offers_ownership', 'invited_by', 'expires_at', 'accepted_at', 'revoked_at', 'revoked_by'])]
class OrganisationInvitation extends Model
{
    /** @use HasFactory<OrganisationInvitationFactory> */
    use HasFactory;

    public static function findByToken(string $token): ?self
    {
        return self::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();
    }

    /**
     * Get the organisation that the invitation belongs to.
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the user who sent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<Party, $this> */
    public function personParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'person_party_id')->withTrashed();
    }

    /** @return HasMany<OrganisationInvitationRoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(OrganisationInvitationRoleAssignment::class);
    }

    /**
     * Determine if the invitation has been accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Determine if the invitation is pending.
     */
    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isRevoked() && ! $this->isExpired();
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isFor(User $user): bool
    {
        return mb_strtolower($this->email) === mb_strtolower($user->email);
    }

    /**
     * Determine if the invitation has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => OrganisationRole::class,
            'offers_ownership' => 'boolean',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
