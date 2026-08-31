<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\User;

class TenantAuditEventPolicy
{
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return in_array($user->organisationRole($organisation), [
            OrganisationRole::OrganisationAdministrator,
            OrganisationRole::ProgramManager,
            OrganisationRole::CaseWorker,
            OrganisationRole::EngagementOfficer,
        ], true);
    }
}
