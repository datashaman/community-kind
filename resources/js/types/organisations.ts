export type OrganisationRole =
    | 'organisation_administrator'
    | 'program_manager'
    | 'case_worker'
    | 'engagement_officer'
    | 'executive_viewer';

export type Organisation = {
    id: number;
    name: string;
    slug: string;
    role?: OrganisationRole | null;
    roleLabel?: string;
    isOwner?: boolean;
    status?: string;
    programIds?: number[];
    isCurrent?: boolean;
};

export type OrganisationMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    person_party: PersonPartyOption;
    role_assignments: RoleAssignment[];
    is_owner: boolean;
    can_manage_roles: boolean;
    can_manage_hold: boolean;
    hold: MembershipHold | null;
};

export type OrganisationInvitation = {
    id: number;
    email: string;
    person_name: string;
    offers_ownership: boolean;
    role_assignments: RoleAssignment[];
    created_at: string;
};

export type OrganisationInvitationContext = {
    code: string;
    email: string;
    organisationName: string;
    offersOwnership: boolean;
};

export type DashboardInvitation = {
    id: number;
    inviterName: string;
    personName: string;
    offersOwnership: boolean;
    roleAssignments: Array<{
        roleLabel: string;
        scopeLabel: string;
    }>;
    organisation: {
        name: string;
        slug: string;
    };
};

export type OrganisationPermissions = {
    canUpdateOrganisation: boolean;
    canDeleteOrganisation: boolean;
    canAddMember: boolean;
    canUpdateMember: boolean;
    canRemoveMember: boolean;
    canCreateInvitation: boolean;
    canCancelInvitation: boolean;
    canTransitionOrganisation: boolean;
    canChangeOrganisationSlug: boolean;
    canTransferOwnership: boolean;
};

export type OrganisationAccessHold = {
    id: string;
    reason: string;
    scope: string;
    accessLevel: string;
    reviewAt: string;
    expiresAt: string | null;
};

export type OrganisationOwnershipTransfer = {
    id: string;
    nomineeUserId: number;
    nomineeName: string;
    nominatedByName: string;
    expiresAt: string;
    canAccept: boolean;
};

export type OrganisationOwnerCandidate = {
    id: number;
    name: string;
};

export type RoleOption = {
    value: OrganisationRole;
    label: string;
};

export type ProgramOption = {
    id: number;
    name: string;
};

export type PersonPartyOption = {
    id: number;
    display_name: string;
};

export type RoleAssignment = {
    id?: number;
    role: OrganisationRole;
    role_label: string;
    program_id: number | null;
    scope_label: string;
};

export type MembershipHold = {
    id: number;
    reason: string;
    review_at: string;
    expires_at: string | null;
};
