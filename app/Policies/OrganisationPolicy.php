<?php

namespace App\Policies;

use App\Enums\OrganisationPermission;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\User;

class OrganisationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Organisation $organisation): bool
    {
        return $user->belongsToOrganisation($organisation);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return (bool) config('organisations.self_service_provisioning');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationPermission($organisation, OrganisationPermission::UpdateOrganisation);
    }

    public function transition(User $user, Organisation $organisation): bool
    {
        return $user->ownsOrganisation($organisation);
    }

    public function changeSlug(User $user, Organisation $organisation): bool
    {
        return $user->ownsOrganisation($organisation);
    }

    public function transferOwnership(User $user, Organisation $organisation): bool
    {
        return $user->ownsOrganisation($organisation);
    }

    /**
     * Determine whether the user can leave the organisation.
     */
    public function leave(User $user, Organisation $organisation): bool
    {
        if (! $user->belongsToOrganisation($organisation)) {
            return false;
        }

        return ! $user->ownsOrganisation($organisation)
            || $organisation->owners()->whereKeyNot($user->id)->exists();
    }

    /**
     * Determine whether the user can add a member to the organisation.
     */
    public function addMember(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationPermission($organisation, OrganisationPermission::AddMember);
    }

    /**
     * Determine whether the user can update a member's role in the organisation.
     */
    public function updateMember(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationPermission($organisation, OrganisationPermission::UpdateMember);
    }

    /**
     * Determine whether the user can remove a member from the organisation.
     */
    public function removeMember(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationPermission($organisation, OrganisationPermission::RemoveMember);
    }

    /**
     * Determine whether the user can invite members to the organisation.
     */
    public function inviteMember(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationPermission($organisation, OrganisationPermission::CreateInvitation);
    }

    /**
     * Determine whether the user can cancel invitations.
     */
    public function cancelInvitation(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationPermission($organisation, OrganisationPermission::CancelInvitation);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organisation $organisation): bool
    {
        return in_array($organisation->status, [OrganisationStatus::Pending, OrganisationStatus::Archived], true)
            && $user->hasOrganisationPermission($organisation, OrganisationPermission::DeleteOrganisation);
    }
}
