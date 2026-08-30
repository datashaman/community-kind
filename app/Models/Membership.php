<?php

namespace App\Models;

use App\Enums\OrganisationRole;
use App\Enums\PartyKind;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $user_id
 * @property int|null $person_party_id
 * @property OrganisationRole|null $role
 * @property bool $is_owner
 * @property Carbon|null $accepted_at
 * @property Carbon|null $ended_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read User $user
 * @property-read Party $personParty
 * @property-read Collection<int, RoleAssignment> $roleAssignments
 * @property-read Collection<int, MembershipHold> $holds
 */
#[Fillable(['organisation_id', 'user_id', 'person_party_id', 'role', 'is_owner', 'accepted_at', 'ended_at'])]
class Membership extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'organisation_members';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    protected static function booted(): void
    {
        static::creating(function (Membership $membership): void {
            $membership->accepted_at ??= Carbon::now();

            if ($membership->person_party_id !== null) {
                $partyExists = Party::withoutGlobalScopes()
                    ->whereKey($membership->person_party_id)
                    ->where('organisation_id', $membership->organisation_id)
                    ->where('kind', PartyKind::Person->value)
                    ->exists();

                if (! $partyExists) {
                    throw new LogicException('A Membership must link to a person Party in the same Organisation.');
                }

                return;
            }

            $organisation = Organisation::query()->findOrFail($membership->organisation_id);
            $user = User::query()->findOrFail($membership->user_id);

            $membership->person_party_id = app(OrganisationContext::class)->run(
                $organisation,
                fn () => Party::query()->create([
                    'kind' => PartyKind::Person,
                    'display_name' => $user->name,
                ])->id,
            );
        });

        static::created(function (Membership $membership): void {
            if ($membership->role === null) {
                return;
            }

            app(OrganisationContext::class)->run(
                $membership->organisation,
                fn () => $membership->roleAssignments()->create([
                    'organisation_id' => $membership->organisation_id,
                    'role' => $membership->role,
                ]),
            );
        });
    }

    /**
     * Get the organisation that the membership belongs to.
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the user that belongs to this membership.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function personParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'person_party_id')->withTrashed();
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'membership_id', 'id');
    }

    /** @return HasMany<MembershipHold, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(MembershipHold::class, 'membership_id', 'id');
    }

    public function isHeld(): bool
    {
        $now = now();

        return $this->holds()
            ->whereNull('released_at')
            ->where('starts_at', '<=', $now)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->exists();
    }

    public function hasRole(OrganisationRole $role, ?Program $program = null): bool
    {
        if ($this->ended_at !== null || $this->isHeld()) {
            return false;
        }

        return $this->roleAssignments()
            ->whereNull('ended_at')
            ->where('role', $role->value)
            ->when(
                $program === null,
                fn (Builder $query) => $query->whereNull('program_id'),
                fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                    ->whereNull('program_id')
                    ->orWhere('program_id', $program->id)),
            )
            ->exists();
    }

    public function end(): void
    {
        if ($this->ended_at !== null) {
            return;
        }

        $endedAt = now();
        $this->update(['ended_at' => $endedAt]);
        $this->roleAssignments()->whereNull('ended_at')->update(['ended_at' => $endedAt]);
    }

    /**
     * Get the programs this membership can access.
     *
     * @return BelongsToMany<Program, $this>
     */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'membership_program', 'membership_id', 'program_id')
            ->withPivotValue('organisation_id', app(OrganisationContext::class)->id());
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
            'is_owner' => 'boolean',
            'accepted_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
