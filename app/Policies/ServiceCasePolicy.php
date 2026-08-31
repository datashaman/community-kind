<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\ServiceCase;
use App\Models\User;

class ServiceCasePolicy
{
    public function view(User $user, ServiceCase $case): bool
    {
        if ($this->managesProgram($user, $case)) {
            return true;
        }

        $membership = $user->organisationMembership($case->organisation);

        return $membership !== null
            && ! $membership->isHeld()
            && $user->hasOrganisationRole($case->organisation, OrganisationRole::CaseWorker, $case->program)
            && $user->hasProgramAccess($case->program)
            && $case->assignments()->where('membership_id', $membership->id)->where('status', 'active')->exists();
    }

    public function update(User $user, ServiceCase $case): bool
    {
        return $this->view($user, $case) && ! $case->status->isTerminal();
    }

    private function managesProgram(User $user, ServiceCase $case): bool
    {
        return $user->hasOrganisationRole($case->organisation, OrganisationRole::ProgramManager, $case->program)
            && $user->hasProgramAccess($case->program);
    }
}
