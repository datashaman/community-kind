<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\SupporterJourney;
use App\Models\User;

class SupporterJourneyPolicy
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
    public function view(User $user, SupporterJourney $supporterJourney): bool
    {
        return $this->viewAny($user, $supporterJourney->organisation);
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
    public function update(User $user, SupporterJourney $supporterJourney): bool
    {
        return $this->view($user, $supporterJourney);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupporterJourney $supporterJourney): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SupporterJourney $supporterJourney): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SupporterJourney $supporterJourney): bool
    {
        return false;
    }
}
