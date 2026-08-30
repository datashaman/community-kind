export type TeamRole =
    | 'team_administrator'
    | 'program_manager'
    | 'case_worker'
    | 'engagement_officer'
    | 'executive_viewer';

export type Team = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    role?: TeamRole | null;
    roleLabel?: string;
    isOwner?: boolean;
    status?: string;
    programIds?: number[];
    isCurrent?: boolean;
};

export type TeamMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    role: TeamRole | null;
    role_label: string;
    is_owner: boolean;
    program_ids: number[];
};

export type TeamInvitation = {
    id: number;
    email: string;
    role: TeamRole;
    role_label: string;
    created_at: string;
};

export type TeamInvitationContext = {
    code: string;
    email: string;
    teamName: string;
};

export type DashboardInvitation = {
    id: number;
    inviterName: string;
    team: {
        name: string;
        slug: string;
    };
};

export type TeamPermissions = {
    canUpdateTeam: boolean;
    canDeleteTeam: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
};

export type RoleOption = {
    value: TeamRole;
    label: string;
};

export type ProgramOption = {
    id: number;
    name: string;
};
