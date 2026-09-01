<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\User;

class OrganisationConfigurationPolicy
{
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator);
    }

    public function view(User $user, OrganisationConfiguration $configuration): bool
    {
        return $this->viewAny($user, $configuration->organisation);
    }

    public function create(User $user, Organisation $organisation): bool
    {
        return $this->viewAny($user, $organisation);
    }

    public function update(User $user, OrganisationConfiguration $configuration): bool
    {
        return $this->view($user, $configuration);
    }

    public function delete(User $user, OrganisationConfiguration $configuration): bool
    {
        return false;
    }
}
