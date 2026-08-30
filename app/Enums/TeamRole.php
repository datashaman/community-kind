<?php

namespace App\Enums;

enum TeamRole: string
{
    /** @deprecated Existing fixtures only. Ownership is stored separately. */
    case Owner = 'owner';
    case TeamAdministrator = 'team_administrator';
    case ProgramManager = 'program_manager';
    case CaseWorker = 'case_worker';
    case EngagementOfficer = 'engagement_officer';
    case ExecutiveViewer = 'executive_viewer';

    public const Admin = self::TeamAdministrator;

    public const Member = self::CaseWorker;

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::TeamAdministrator => 'Team administrator',
            self::ProgramManager => 'Program manager',
            self::CaseWorker => 'Case worker',
            self::EngagementOfficer => 'Engagement officer',
            self::ExecutiveViewer => 'Executive viewer',
        };
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::TeamAdministrator => [
                TeamPermission::UpdateTeam,
                TeamPermission::AddMember,
                TeamPermission::UpdateMember,
                TeamPermission::RemoveMember,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
            ],
            self::ProgramManager, self::CaseWorker, self::EngagementOfficer, self::ExecutiveViewer => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the roles that can be assigned to team members (excludes Owner).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
