<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Donation;
use App\Models\Organisation;
use App\Models\User;

class DonationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organisation $organisation): bool
    {
        $membership = $user->organisationMembership($organisation);

        return $membership !== null
            && ! $membership->isHeld()
            && $membership->roleAssignments()->whereNull('ended_at')->where('role', OrganisationRole::EngagementOfficer)->exists();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Donation $donation): bool
    {
        return $this->viewAny($user, $donation->organisation);
    }
}
