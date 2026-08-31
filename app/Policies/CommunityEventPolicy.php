<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\CommunityEvent;
use App\Models\Organisation;
use App\Models\User;

class CommunityEventPolicy
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
    public function view(User $user, CommunityEvent $communityEvent): bool
    {
        return $user->hasOrganisationRole($communityEvent->organisation, OrganisationRole::EngagementOfficer);
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
    public function update(User $user, CommunityEvent $communityEvent): bool
    {
        return $this->view($user, $communityEvent);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CommunityEvent $communityEvent): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CommunityEvent $communityEvent): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CommunityEvent $communityEvent): bool
    {
        return false;
    }
}
