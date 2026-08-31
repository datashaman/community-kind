<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\User;

class PartyPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator)
            || $this->hasRoleInAnyScope($user, $organisation, OrganisationRole::ProgramManager);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Party $party): bool
    {
        if ($user->hasOrganisationRole($party->organisation, OrganisationRole::OrganisationAdministrator)) {
            return true;
        }

        if ($user->hasOrganisationRole($party->organisation, OrganisationRole::ProgramManager)) {
            return true;
        }

        return $party->programs()
            ->get()
            ->contains(fn ($program): bool => $user->hasOrganisationRole(
                $party->organisation,
                OrganisationRole::ProgramManager,
                $program,
            ));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Party $party): bool
    {
        return $user->hasOrganisationRole($party->organisation, OrganisationRole::OrganisationAdministrator);
    }

    public function recordConsent(User $user, Party $party): bool
    {
        return $this->view($user, $party);
    }

    public function manageSafeContact(User $user, Party $party): bool
    {
        if ($user->hasOrganisationRole($party->organisation, OrganisationRole::ProgramManager)) {
            return true;
        }

        return $party->programs()
            ->get()
            ->contains(fn ($program): bool => $user->hasOrganisationRole(
                $party->organisation,
                OrganisationRole::ProgramManager,
                $program,
            ));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Party $party): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Party $party): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Party $party): bool
    {
        return false;
    }

    private function hasRoleInAnyScope(User $user, Organisation $organisation, OrganisationRole $role): bool
    {
        $membership = $user->organisationMembership($organisation);

        if ($membership === null || $membership->isHeld()) {
            return false;
        }

        return $membership->roleAssignments()
            ->whereNull('ended_at')
            ->where('role', $role)
            ->exists();
    }
}
