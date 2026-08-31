<?php

namespace App\Policies;

use App\Authorization\CaseAccess;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\ServiceCase;
use App\Models\User;

class PartyPolicy
{
    public function __construct(private readonly CaseAccess $caseAccess) {}

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Organisation $organisation): bool
    {
        return $user->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator)
            || $this->hasRoleInAnyScope($user, $organisation, OrganisationRole::ProgramManager)
            || $this->hasRoleInAnyScope($user, $organisation, OrganisationRole::CaseWorker)
            || $this->hasRoleInAnyScope($user, $organisation, OrganisationRole::EngagementOfficer);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Party $party): bool
    {
        if ($user->hasOrganisationRole($party->organisation, OrganisationRole::OrganisationAdministrator)) {
            return true;
        }

        if ($user->hasOrganisationRole($party->organisation, OrganisationRole::ProgramManager)) {
            return true;
        }

        if ($party->programs()
            ->get()
            ->contains(fn ($program): bool => $user->hasOrganisationRole(
                $party->organisation,
                OrganisationRole::ProgramManager,
                $program,
            ))) {
            return true;
        }

        if ($this->supporterSafe($user, $party)) {
            return true;
        }

        return ServiceCase::query()
            ->where('party_id', $party->id)
            ->get()
            ->contains(fn (ServiceCase $case): bool => $this->caseAccess->canViewConfidential($user, $case));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Organisation $organisation): bool
    {
        return $this->hasRoleInAnyScope($user, $organisation, OrganisationRole::ProgramManager);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Party $party): bool
    {
        return $this->hasProgramManagerAccess($user, $party);
    }

    public function recordConsent(User $user, Party $party): bool
    {
        return $this->supporterSafe($user, $party)
            || $this->hasProgramManagerAccess($user, $party)
            || ServiceCase::query()
                ->where('party_id', $party->id)
                ->get()
                ->contains(fn (ServiceCase $case): bool => $this->caseAccess->canViewConfidential($user, $case));
    }

    public function manageSafeContact(User $user, Party $party): bool
    {
        return ServiceCase::query()
            ->where('party_id', $party->id)
            ->get()
            ->contains(fn (ServiceCase $case): bool => $this->caseAccess->canViewSensitive($user, $case));
    }

    public function supporterSafe(User $user, Party $party): bool
    {
        return $this->hasRoleInAnyScope($user, $party->organisation, OrganisationRole::EngagementOfficer)
            && $party->businessRoles()->whereIn('role', ['donor', 'volunteer', 'partner_contact', 'event_attendee'])->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Party $party): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Party $party): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Party $party): bool
    {
        return false;
    }

    private function hasRoleInAnyScope(User $user, Organisation $organisation, OrganisationRole $role): bool
    {
        $membership = $user->organisationMembership($organisation);

        if ($membership === null || $membership->isHeld()) {
            return false;
        }

        return $membership->roleAssignments()
            ->whereNull('ended_at')
            ->where('role', $role)
            ->exists();
    }

    private function hasProgramManagerAccess(User $user, Party $party): bool
    {
        if ($user->hasOrganisationRole($party->organisation, OrganisationRole::ProgramManager)) {
            return true;
        }

        return $party->programs()->get()->contains(fn ($program): bool => $user->hasOrganisationRole(
            $party->organisation,
            OrganisationRole::ProgramManager,
            $program,
        ));
    }
}
