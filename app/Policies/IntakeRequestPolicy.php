<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Models\IntakeRequest;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\User;

class IntakeRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return $this->hasOperationalRole($user, $organisation);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, IntakeRequest $intakeRequest): bool
    {
        if ($this->canManageProgram($user, $intakeRequest->program)) {
            return true;
        }

        $membership = $user->organisationMembership($intakeRequest->organisation);

        return $membership !== null
            && $user->hasOrganisationRole($intakeRequest->organisation, OrganisationRole::CaseWorker, $intakeRequest->program)
            && $user->hasProgramAccess($intakeRequest->program)
            && $intakeRequest->serviceCase?->assignments()
                ->where('membership_id', $membership->id)
                ->where('status', 'active')
                ->exists() === true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Program $program): bool
    {
        return $this->canManageProgram($user, $program)
            || ($user->hasOrganisationRole($program->organisation, OrganisationRole::CaseWorker, $program)
                && $user->hasProgramAccess($program));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->canManageProgram($user, $intakeRequest->program);
    }

    public function transition(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->update($user, $intakeRequest);
    }

    public function reviewDuplicate(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->update($user, $intakeRequest);
    }

    public function assign(User $user, IntakeRequest $intakeRequest): bool
    {
        return $this->update($user, $intakeRequest);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, IntakeRequest $intakeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, IntakeRequest $intakeRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, IntakeRequest $intakeRequest): bool
    {
        return false;
    }

    private function hasOperationalRole(User $user, Organisation $organisation): bool
    {
        $membership = $user->organisationMembership($organisation);

        return $membership !== null
            && ! $membership->isHeld()
            && $membership->roleAssignments()
                ->whereNull('ended_at')
                ->whereIn('role', [OrganisationRole::ProgramManager, OrganisationRole::CaseWorker])
                ->exists();
    }

    private function canManageProgram(User $user, Program $program): bool
    {
        return $user->hasOrganisationRole($program->organisation, OrganisationRole::ProgramManager, $program)
            && $user->hasProgramAccess($program);
    }
}
