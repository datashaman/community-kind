<?php

namespace App\Authorization;

use App\Enums\CaseClassification;
use App\Enums\OrganisationRole;
use App\Enums\RestrictedAccessPermission;
use App\Models\CaseDocument;
use App\Models\Membership;
use App\Models\Program;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use App\Models\User;

class CaseAccess
{
    public function canView(User $user, ServiceCase $case): bool
    {
        if (! $this->canViewConfidential($user, $case)) {
            return false;
        }

        return $case->confidentiality === CaseClassification::Confidential
            || $this->hasGrant($user, $case, RestrictedAccessPermission::SensitiveData);
    }

    public function canViewConfidential(User $user, ServiceCase $case): bool
    {
        if ($user->hasOrganisationRole($case->organisation, OrganisationRole::ProgramManager, $case->program)
            && $user->hasProgramAccess($case->program)) {
            return true;
        }

        $membership = $this->membership($user, $case);

        return $membership !== null
            && $user->hasOrganisationRole($case->organisation, OrganisationRole::CaseWorker, $case->program)
            && $user->hasProgramAccess($case->program)
            && $case->assignments()->where('membership_id', $membership->id)->where('status', 'active')->exists();
    }

    public function canViewSensitive(User $user, ServiceCase $case): bool
    {
        return $this->canViewConfidential($user, $case)
            && $this->hasGrant($user, $case, RestrictedAccessPermission::SensitiveData);
    }

    public function canViewDocument(User $user, CaseDocument $document): bool
    {
        $case = $document->serviceCase;

        if (! $this->canView($user, $case)) {
            return false;
        }

        return $document->classification === CaseClassification::Confidential
            || $this->canViewSensitive($user, $case);
    }

    public function canManageAccess(User $user, ServiceCase $case): bool
    {
        return $this->canManageProgram($user, $case->program);
    }

    public function canManageProgram(User $user, Program $program): bool
    {
        return $user->hasOrganisationRole($program->organisation, OrganisationRole::ProgramManager, $program)
            && $user->hasProgramAccess($program);
    }

    public function canExport(User $user, ServiceCase $case): bool
    {
        return $this->canManageAccess($user, $case)
            && $this->hasGrant($user, $case, RestrictedAccessPermission::IdentifiableCaseExport);
    }

    public function canExportProgram(User $user, Program $program): bool
    {
        $membership = $user->organisationMembership($program->organisation);

        return $membership !== null
            && ! $membership->isHeld()
            && $user->hasOrganisationRole($program->organisation, OrganisationRole::ProgramManager, $program)
            && $user->hasProgramAccess($program)
            && RestrictedAccessGrant::query()
                ->active()
                ->where('membership_id', $membership->id)
                ->where('program_id', $program->id)
                ->whereNull('service_case_id')
                ->where('permission', RestrictedAccessPermission::IdentifiableCaseExport)
                ->exists();
    }

    public function hasGrant(User $user, ServiceCase $case, RestrictedAccessPermission $permission): bool
    {
        $membership = $this->membership($user, $case);

        if ($membership === null) {
            return false;
        }

        return RestrictedAccessGrant::query()
            ->active()
            ->where('membership_id', $membership->id)
            ->where('program_id', $case->program_id)
            ->where('permission', $permission)
            ->when(
                $permission === RestrictedAccessPermission::SensitiveData,
                fn ($query) => $query->where('service_case_id', $case->id),
                fn ($query) => $query->whereNull('service_case_id'),
            )
            ->exists();
    }

    private function membership(User $user, ServiceCase $case): ?Membership
    {
        $membership = $user->organisationMembership($case->organisation);

        return $membership !== null && ! $membership->isHeld() ? $membership : null;
    }
}
