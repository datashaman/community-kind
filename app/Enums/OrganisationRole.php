<?php

namespace App\Enums;

enum OrganisationRole: string
{
    case OrganisationAdministrator = 'organisation_administrator';
    case ProgramManager = 'program_manager';
    case CaseWorker = 'case_worker';
    case EngagementOfficer = 'engagement_officer';
    case ExecutiveViewer = 'executive_viewer';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::OrganisationAdministrator => 'Organisation administrator',
            self::ProgramManager => 'Program manager',
            self::CaseWorker => 'Case worker',
            self::EngagementOfficer => 'Engagement officer',
            self::ExecutiveViewer => 'Executive viewer',
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<OrganisationPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::OrganisationAdministrator => [
                OrganisationPermission::UpdateOrganisation,
                OrganisationPermission::AddMember,
                OrganisationPermission::UpdateMember,
                OrganisationPermission::RemoveMember,
                OrganisationPermission::CreateInvitation,
                OrganisationPermission::CancelInvitation,
            ],
            self::ProgramManager, self::CaseWorker, self::EngagementOfficer, self::ExecutiveViewer => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(OrganisationPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the operational roles that can be assigned to Organisation members.
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
