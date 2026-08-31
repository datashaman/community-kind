<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\AudienceSegment;
use App\Models\Organisation;
use App\Models\User;

class AudienceSegmentPolicy
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
    public function view(User $user, AudienceSegment $audienceSegment): bool
    {
        return $this->viewAny($user, $audienceSegment->organisation);
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
    public function update(User $user, AudienceSegment $audienceSegment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AudienceSegment $audienceSegment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AudienceSegment $audienceSegment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AudienceSegment $audienceSegment): bool
    {
        return false;
    }
}
