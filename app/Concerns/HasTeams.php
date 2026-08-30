<?php

namespace App\Concerns;

use App\Data\TeamPermissions;
use App\Data\UserTeam;
use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Program;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasTeams
{
    /**
     * Get all of the teams the user belongs to.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members', 'user_id', 'team_id')
            ->withPivot(['role', 'is_owner'])
            ->withTimestamps();
    }

    /**
     * Get all of the teams the user owns.
     *
     * @return HasManyThrough<Team, Membership, $this>
     */
    public function ownedTeams(): HasManyThrough
    {
        return $this->hasManyThrough(
            Team::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'team_id',
        )->where('team_members.is_owner', true);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function teamMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current team.
     *
     * @return BelongsTo<Team, $this>
     */
    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    /**
     * Get the user's personal team.
     */
    public function personalTeam(): ?Team
    {
        return $this->teams()
            ->where('is_personal', true)
            ->first();
    }

    /**
     * Switch to the given team.
     */
    public function switchTeam(Team $team): bool
    {
        if (! $this->belongsToTeam($team)) {
            return false;
        }

        $this->update(['current_team_id' => $team->id]);
        $this->setRelation('currentTeam', $team);

        URL::defaults(['current_team' => $team->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given team.
     */
    public function belongsToTeam(Team $team): bool
    {
        return $this->teams()->where('teams.id', $team->id)->exists();
    }

    /**
     * Determine if the given team is the user's current team.
     */
    public function isCurrentTeam(Team $team): bool
    {
        return $this->current_team_id === $team->id;
    }

    /**
     * Determine if the user is the owner of the given team.
     */
    public function ownsTeam(Team $team): bool
    {
        $membership = $this->teamMembership($team);

        if ($membership === null) {
            return false;
        }

        return $membership->is_owner;
    }

    /**
     * Get the user's role on the given team.
     */
    public function teamRole(Team $team): ?TeamRole
    {
        return $this->teamMembership($team)?->role;
    }

    /**
     * Get the user's membership for the given Team.
     */
    public function teamMembership(Team $team): ?Membership
    {
        return $this->teamMemberships()
            ->where('team_id', $team->id)
            ->first();
    }

    /**
     * Determine whether the user has access to a program through its Team membership.
     */
    public function hasProgramAccess(Program $program): bool
    {
        return $this->teamMemberships()
            ->where('team_id', $program->team_id)
            ->whereHas('programs', fn ($query) => $query->whereKey($program->id))
            ->exists();
    }

    /**
     * Get the user's teams as a collection of UserTeam objects.
     *
     * @return Collection<int, UserTeam>
     */
    public function toUserTeams(bool $includeCurrent = false): Collection
    {
        return $this->teamMemberships()
            ->with(['team', 'programs'])
            ->get()
            ->map(function (Membership $membership) use ($includeCurrent) {
                $team = $membership->team;

                return ! $includeCurrent && $this->isCurrentTeam($team)
                    ? null
                    : $this->toUserTeam($team, $membership);
            })
            ->filter()
            ->values();
    }

    /**
     * Get the user's team as a UserTeam object.
     */
    public function toUserTeam(Team $team, ?Membership $membership = null): UserTeam
    {
        $membership ??= $this->teamMembership($team);
        $role = $membership?->role;

        return new UserTeam(
            id: $team->id,
            name: $team->name,
            slug: $team->slug,
            isPersonal: $team->is_personal,
            role: $role?->value,
            roleLabel: $role?->label(),
            isOwner: $membership !== null && $membership->is_owner,
            status: $team->status->value,
            programIds: $membership === null
                ? []
                : $membership->programs->sortBy('id')->pluck('id')->values()->all(),
            isCurrent: $this->isCurrentTeam($team),
        );
    }

    /**
     * Get the standard permissions for a team as a TeamPermissions object.
     */
    public function toTeamPermissions(Team $team): TeamPermissions
    {
        return new TeamPermissions(
            canUpdateTeam: $this->hasTeamPermission($team, TeamPermission::UpdateTeam),
            canDeleteTeam: $this->hasTeamPermission($team, TeamPermission::DeleteTeam),
            canAddMember: $this->hasTeamPermission($team, TeamPermission::AddMember),
            canUpdateMember: $this->hasTeamPermission($team, TeamPermission::UpdateMember),
            canRemoveMember: $this->hasTeamPermission($team, TeamPermission::RemoveMember),
            canCreateInvitation: $this->hasTeamPermission($team, TeamPermission::CreateInvitation),
            canCancelInvitation: $this->hasTeamPermission($team, TeamPermission::CancelInvitation),
        );
    }

    public function fallbackTeam(?Team $excluding = null): ?Team
    {
        return $this->teams()
            ->when($excluding, fn ($query) => $query->where('teams.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(teams.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the team.
     */
    public function hasTeamPermission(Team $team, TeamPermission $permission): bool
    {
        return $this->ownsTeam($team)
            || ($this->teamRole($team)?->hasPermission($permission) ?? false);
    }
}
