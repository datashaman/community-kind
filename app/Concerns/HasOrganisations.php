<?php

namespace App\Concerns;

use App\Data\OrganisationPermissions;
use App\Data\UserOrganisation;
use App\Enums\OrganisationPermission;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Program;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

trait HasOrganisations
{
    /**
     * Get all of the organisations the user belongs to.
     *
     * @return BelongsToMany<Organisation, $this>
     */
    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_members', 'user_id', 'organisation_id')
            ->withPivot(['role', 'is_owner'])
            ->withTimestamps();
    }

    /**
     * Get all of the organisations the user owns.
     *
     * @return HasManyThrough<Organisation, Membership, $this>
     */
    public function ownedOrganisations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Organisation::class,
            Membership::class,
            'user_id',
            'id',
            'id',
            'organisation_id',
        )->where('organisation_members.is_owner', true);
    }

    /**
     * Get all of the memberships for the user.
     *
     * @return HasMany<Membership, $this>
     */
    public function organisationMemberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'user_id');
    }

    /**
     * Get the user's current organisation.
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function currentOrganisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'current_organisation_id');
    }

    /**
     * Switch to the given organisation.
     */
    public function switchOrganisation(Organisation $organisation): bool
    {
        if (! $this->belongsToOrganisation($organisation)) {
            return false;
        }

        $this->update(['current_organisation_id' => $organisation->id]);
        $this->setRelation('currentOrganisation', $organisation);

        URL::defaults(['current_organisation' => $organisation->slug]);

        return true;
    }

    /**
     * Determine if the user belongs to the given organisation.
     */
    public function belongsToOrganisation(Organisation $organisation): bool
    {
        return $this->organisations()->where('organisations.id', $organisation->id)->exists();
    }

    /**
     * Determine if the given organisation is the user's current organisation.
     */
    public function isCurrentOrganisation(Organisation $organisation): bool
    {
        return $this->current_organisation_id === $organisation->id;
    }

    /**
     * Determine if the user is the owner of the given organisation.
     */
    public function ownsOrganisation(Organisation $organisation): bool
    {
        $membership = $this->organisationMembership($organisation);

        if ($membership === null) {
            return false;
        }

        return $membership->is_owner;
    }

    /**
     * Get the user's role on the given organisation.
     */
    public function organisationRole(Organisation $organisation): ?OrganisationRole
    {
        return $this->organisationMembership($organisation)?->role;
    }

    /**
     * Get the user's membership for the given Organisation.
     */
    public function organisationMembership(Organisation $organisation): ?Membership
    {
        return $this->organisationMemberships()
            ->where('organisation_id', $organisation->id)
            ->first();
    }

    /**
     * Determine whether the user has access to a program through its Organisation membership.
     */
    public function hasProgramAccess(Program $program): bool
    {
        return $this->organisationMemberships()
            ->where('organisation_id', $program->organisation_id)
            ->whereHas('programs', fn ($query) => $query->whereKey($program->id))
            ->exists();
    }

    /**
     * Get the user's organisations as a collection of UserOrganisation objects.
     *
     * @return Collection<int, UserOrganisation>
     */
    public function toUserOrganisations(bool $includeCurrent = false): Collection
    {
        return $this->organisationMemberships()
            ->with('organisation')
            ->get()
            ->map(function (Membership $membership) use ($includeCurrent) {
                $organisation = $membership->organisation;

                app(OrganisationContext::class)->run(
                    $organisation,
                    fn () => $membership->load('programs'),
                );

                return ! $includeCurrent && $this->isCurrentOrganisation($organisation)
                    ? null
                    : $this->toUserOrganisation($organisation, $membership);
            })
            ->filter()
            ->values();
    }

    /**
     * Get the user's organisation as a UserOrganisation object.
     */
    public function toUserOrganisation(Organisation $organisation, ?Membership $membership = null): UserOrganisation
    {
        $membership ??= $this->organisationMembership($organisation);
        $role = $membership?->role;

        return new UserOrganisation(
            id: $organisation->id,
            name: $organisation->name,
            slug: $organisation->slug,
            role: $role?->value,
            roleLabel: $role?->label(),
            isOwner: $membership !== null && $membership->is_owner,
            status: $organisation->status->value,
            programIds: $membership === null
                ? []
                : $membership->programs->sortBy('id')->pluck('id')->values()->all(),
            isCurrent: $this->isCurrentOrganisation($organisation),
        );
    }

    /**
     * Get the standard permissions for an organisation as an OrganisationPermissions object.
     */
    public function toOrganisationPermissions(Organisation $organisation): OrganisationPermissions
    {
        return new OrganisationPermissions(
            canUpdateOrganisation: $this->hasOrganisationPermission($organisation, OrganisationPermission::UpdateOrganisation),
            canDeleteOrganisation: in_array($organisation->status, [OrganisationStatus::Pending, OrganisationStatus::Archived], true)
                && $this->hasOrganisationPermission($organisation, OrganisationPermission::DeleteOrganisation),
            canAddMember: $this->hasOrganisationPermission($organisation, OrganisationPermission::AddMember),
            canUpdateMember: $this->hasOrganisationPermission($organisation, OrganisationPermission::UpdateMember),
            canRemoveMember: $this->hasOrganisationPermission($organisation, OrganisationPermission::RemoveMember),
            canCreateInvitation: $this->hasOrganisationPermission($organisation, OrganisationPermission::CreateInvitation),
            canCancelInvitation: $this->hasOrganisationPermission($organisation, OrganisationPermission::CancelInvitation),
            canTransitionOrganisation: $this->ownsOrganisation($organisation),
            canChangeOrganisationSlug: $this->ownsOrganisation($organisation)
                && in_array($organisation->status, [OrganisationStatus::Pending, OrganisationStatus::Active], true),
            canTransferOwnership: $this->ownsOrganisation($organisation)
                && in_array($organisation->status, [OrganisationStatus::Pending, OrganisationStatus::Active], true),
        );
    }

    public function fallbackOrganisation(?Organisation $excluding = null): ?Organisation
    {
        return $this->organisations()
            ->when($excluding, fn ($query) => $query->where('organisations.id', '!=', $excluding->id))
            ->orderByRaw('LOWER(organisations.name)')
            ->first();
    }

    /**
     * Determine if the user has the given permission on the organisation.
     */
    public function hasOrganisationPermission(Organisation $organisation, OrganisationPermission $permission): bool
    {
        return $this->ownsOrganisation($organisation)
            || ($this->organisationRole($organisation)?->hasPermission($permission) ?? false);
    }
}
