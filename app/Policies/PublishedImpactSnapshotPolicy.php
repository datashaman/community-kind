<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\PublishedImpactSnapshot;
use App\Models\User;

class PublishedImpactSnapshotPolicy
{
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationRole($organisation, OrganisationRole::ExecutiveViewer);
    }

    public function view(User $user, PublishedImpactSnapshot $snapshot): bool
    {
        return $this->viewAny($user, $snapshot->organisation);
    }

    public function create(User $user, Organisation $organisation): bool
    {
        return $this->viewAny($user, $organisation);
    }

    public function update(User $user, PublishedImpactSnapshot $snapshot): bool
    {
        return false;
    }

    public function delete(User $user, PublishedImpactSnapshot $snapshot): bool
    {
        return false;
    }
}
