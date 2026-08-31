<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VolunteerOpportunity;

class VolunteerOpportunityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationRole($organisation, OrganisationRole::EngagementOfficer);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, VolunteerOpportunity $volunteerOpportunity): bool
    {
        return $user->hasOrganisationRole($volunteerOpportunity->organisation, OrganisationRole::EngagementOfficer);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Organisation $organisation): bool
    {
        return $this->viewAny($user, $organisation);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, VolunteerOpportunity $volunteerOpportunity): bool
    {
        return $this->view($user, $volunteerOpportunity);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, VolunteerOpportunity $volunteerOpportunity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, VolunteerOpportunity $volunteerOpportunity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, VolunteerOpportunity $volunteerOpportunity): bool
    {
        return false;
    }
}
