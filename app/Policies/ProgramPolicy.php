<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return $user->organisationRole($organisation) === OrganisationRole::OrganisationAdministrator;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Program $program): bool
    {
        $role = $user->organisationRole($program->organisation);

        return $role === OrganisationRole::OrganisationAdministrator
            || ($role !== null && $user->hasProgramAccess($program));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Program $program): bool
    {
        $role = $user->organisationRole($program->organisation);

        return $role === OrganisationRole::OrganisationAdministrator
            || ($role === OrganisationRole::ProgramManager && $user->hasProgramAccess($program));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Program $program): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Program $program): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Program $program): bool
    {
        return false;
    }
}
