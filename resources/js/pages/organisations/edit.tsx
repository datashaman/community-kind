import { Form, Head, router } from '@inertiajs/react';
import { Mail, Plus, ShieldAlert, UserPlus, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import CancelInvitationModal from '@/components/cancel-invitation-modal';
import DeleteOrganisationModal from '@/components/delete-organisation-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import InviteMemberModal from '@/components/invite-member-modal';
import RemoveMemberModal from '@/components/remove-member-modal';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/hooks/use-initials';
import { edit, index, update } from '@/routes/organisations';
import { update as updateLifecycle } from '@/routes/organisations/lifecycle';
import { update as updateMember } from '@/routes/organisations/members';
import {
    destroy as releaseMembershipHold,
    store as createMembershipHold,
} from '@/routes/organisations/members/holds';
import {
    store as storeOwnershipTransfer,
    update as acceptOwnershipTransfer,
} from '@/routes/organisations/ownership-transfers';
import { update as updateSlug } from '@/routes/organisations/slug';
import type {
    OrganisationAccessHold,
    OrganisationOwnerCandidate,
    OrganisationOwnershipTransfer,
    ProgramOption,
    PersonPartyOption,
    RoleOption,
    RoleAssignment,
    Organisation,
    OrganisationInvitation,
    OrganisationMember,
    OrganisationPermissions,
} from '@/types';

type Props = {
    organisation: Organisation;
    members: OrganisationMember[];
    invitations: OrganisationInvitation[];
    permissions: OrganisationPermissions;
    availableRoles: RoleOption[];
    programs: ProgramOption[];
    personParties: PersonPartyOption[];
    allowedTransitions: string[];
    ownerCandidates: OrganisationOwnerCandidate[];
    ownershipTransfer: OrganisationOwnershipTransfer | null;
    accessHolds: OrganisationAccessHold[];
};

export default function OrganisationEdit({
    organisation,
    members,
    invitations,
    permissions,
    availableRoles,
    programs,
    personParties,
    allowedTransitions,
    ownerCandidates,
    ownershipTransfer,
    accessHolds,
}: Props) {
    const getInitials = useInitials();

    const [inviteDialogOpen, setInviteDialogOpen] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [removeMemberDialogOpen, setRemoveMemberDialogOpen] = useState(false);
    const [memberToRemove, setMemberToRemove] =
        useState<OrganisationMember | null>(null);
    const [cancelInvitationDialogOpen, setCancelInvitationDialogOpen] =
        useState(false);
    const [invitationToCancel, setInvitationToCancel] =
        useState<OrganisationInvitation | null>(null);

    const pageTitle = useMemo(
        () =>
            permissions.canUpdateOrganisation
                ? `Edit ${organisation.name}`
                : `View ${organisation.name}`,
        [permissions.canUpdateOrganisation, organisation.name],
    );

    const statusLabel = (status: string) =>
        status
            .replaceAll('_', ' ')
            .replace(/^./, (letter) => letter.toUpperCase());

    const confirmRemoveMember = (member: OrganisationMember) => {
        setMemberToRemove(member);
        setRemoveMemberDialogOpen(true);
    };

    const confirmCancelInvitation = (invitation: OrganisationInvitation) => {
        setInvitationToCancel(invitation);
        setCancelInvitationDialogOpen(true);
    };

    return (
        <>
            <Head title={pageTitle} />

            <h1 className="sr-only">{pageTitle}</h1>

            <div className="flex flex-col space-y-10">
                <div className="space-y-6">
                    <div className="flex items-center gap-3">
                        <Badge variant="outline">
                            {statusLabel(organisation.status ?? 'pending')}
                        </Badge>
                    </div>

                    {accessHolds.map((hold) => (
                        <div
                            key={hold.id}
                            className="border-destructive/40 bg-destructive/5 flex gap-3 rounded-lg border p-4"
                            data-test="organisation-access-hold"
                        >
                            <ShieldAlert className="text-destructive mt-0.5 size-5 shrink-0" />
                            <div className="space-y-1">
                                <p className="font-medium">
                                    Access hold: {statusLabel(hold.accessLevel)}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {hold.reason}
                                </p>
                                <p className="text-muted-foreground text-xs">
                                    Scope: {statusLabel(hold.scope)} · Review by{' '}
                                    {new Date(hold.reviewAt).toLocaleString()}
                                </p>
                            </div>
                        </div>
                    ))}

                    {permissions.canUpdateOrganisation ? (
                        <>
                            <Heading
                                variant="small"
                                title="Organisation settings"
                                description="Update your organisation name and settings"
                            />

                            <Form
                                {...update.form(organisation.slug)}
                                className="space-y-6"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">
                                                Organisation name
                                            </Label>
                                            <Input
                                                id="name"
                                                name="name"
                                                data-test="organisation-name-input"
                                                defaultValue={organisation.name}
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <Button
                                                type="submit"
                                                data-test="organisation-save-button"
                                                disabled={processing}
                                            >
                                                Save
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </>
                    ) : (
                        <>
                            <Heading
                                variant="small"
                                title={organisation.name}
                            />
                        </>
                    )}
                </div>

                {permissions.canChangeOrganisationSlug ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Organisation address"
                            description="Changing the slug redirects the old address temporarily, then quarantines it"
                        />
                        <Form
                            {...updateSlug.form(organisation.slug)}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="slug">Slug</Label>
                                        <Input
                                            id="slug"
                                            name="slug"
                                            defaultValue={organisation.slug}
                                            required
                                        />
                                        <InputError message={errors.slug} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Change slug
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                ) : null}

                {permissions.canTransitionOrganisation &&
                allowedTransitions.length > 0 ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Organisation lifecycle"
                            description="Lifecycle changes are audited and immediately recompute access"
                        />
                        <div className="flex flex-wrap gap-3">
                            {allowedTransitions.map((status) => (
                                <Form
                                    key={status}
                                    {...updateLifecycle.form(organisation.slug)}
                                >
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="status"
                                                value={status}
                                            />
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Move to {statusLabel(status)}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ))}
                        </div>
                    </div>
                ) : null}

                {ownershipTransfer ? (
                    <div className="space-y-4 rounded-lg border p-4">
                        <Heading
                            variant="small"
                            title="Pending ownership transfer"
                            description={`${ownershipTransfer.nominatedByName} nominated ${ownershipTransfer.nomineeName}`}
                        />
                        {ownershipTransfer.canAccept ? (
                            <Form
                                {...acceptOwnershipTransfer.form([
                                    organisation.slug,
                                    ownershipTransfer.id,
                                ])}
                            >
                                {({ processing }) => (
                                    <Button type="submit" disabled={processing}>
                                        Accept ownership
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </div>
                ) : permissions.canTransferOwnership &&
                  ownerCandidates.length > 0 ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Transfer ownership"
                            description="The nominated member must explicitly accept within 72 hours"
                        />
                        <Form
                            {...storeOwnershipTransfer.form(organisation.slug)}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="nominee_user_id">
                                            New owner
                                        </Label>
                                        <select
                                            id="nominee_user_id"
                                            name="nominee_user_id"
                                            className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                            required
                                        >
                                            <option value="">
                                                Select a member
                                            </option>
                                            {ownerCandidates.map(
                                                (candidate) => (
                                                    <option
                                                        key={candidate.id}
                                                        value={candidate.id}
                                                    >
                                                        {candidate.name}
                                                    </option>
                                                ),
                                            )}
                                        </select>
                                        <InputError
                                            message={errors.nominee_user_id}
                                        />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Nominate new owner
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                ) : null}

                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title="Organisation members"
                            description={
                                permissions.canCreateInvitation
                                    ? 'Manage who belongs to this organisation'
                                    : ''
                            }
                        />

                        {permissions.canCreateInvitation ? (
                            <Button
                                data-test="invite-member-button"
                                onClick={() => setInviteDialogOpen(true)}
                            >
                                <UserPlus /> Invite member
                            </Button>
                        ) : null}
                    </div>

                    <div className="space-y-3">
                        {members.map((member) => (
                            <div
                                key={member.id}
                                data-test="member-row"
                                className="space-y-4 rounded-lg border p-4"
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <div className="flex items-center gap-4">
                                        <Avatar className="h-10 w-10">
                                            {member.avatar ? (
                                                <AvatarImage
                                                    src={member.avatar}
                                                    alt={member.name}
                                                />
                                            ) : null}
                                            <AvatarFallback>
                                                {getInitials(member.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div className="font-medium">
                                                {member.name}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                {member.email}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2">
                                        {member.is_owner ? (
                                            <Badge variant="secondary">
                                                Owner
                                            </Badge>
                                        ) : null}

                                        {!member.is_owner &&
                                        permissions.canRemoveMember ? (
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            data-test="member-remove-button"
                                                            onClick={() =>
                                                                confirmRemoveMember(
                                                                    member,
                                                                )
                                                            }
                                                        >
                                                            <X className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Remove member</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        ) : null}
                                    </div>
                                </div>

                                <div className="border-t pt-4">
                                    <p className="text-muted-foreground mb-3 text-xs">
                                        Person Party:{' '}
                                        {member.person_party.display_name}
                                    </p>
                                    <MemberRoleAssignments
                                        organisation={organisation}
                                        member={member}
                                        availableRoles={availableRoles}
                                        programs={programs}
                                    />
                                    {permissions.canUpdateMember ? (
                                        <MembershipHoldControls
                                            organisation={organisation}
                                            member={member}
                                        />
                                    ) : null}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {invitations.length > 0 ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Pending invitations"
                            description="Invitations that haven't been accepted yet"
                        />

                        <div className="space-y-3">
                            {invitations.map((invitation) => (
                                <div
                                    key={invitation.id}
                                    data-test="invitation-row"
                                    className="flex items-center justify-between rounded-lg border p-4"
                                >
                                    <div className="flex items-center gap-4">
                                        <div className="bg-muted flex h-10 w-10 items-center justify-center rounded-full">
                                            <Mail className="text-muted-foreground h-5 w-5" />
                                        </div>
                                        <div>
                                            <div className="font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="text-muted-foreground text-sm">
                                                {invitation.person_name} ·{' '}
                                                {invitation.role_assignments
                                                    .map(
                                                        (assignment) =>
                                                            `${assignment.role_label} (${assignment.scope_label})`,
                                                    )
                                                    .join(', ')}
                                                {invitation.offers_ownership
                                                    ? ' · Owner responsibility offered'
                                                    : ''}
                                            </div>
                                        </div>
                                    </div>

                                    {permissions.canCancelInvitation ? (
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="invitation-cancel-button"
                                                        onClick={() =>
                                                            confirmCancelInvitation(
                                                                invitation,
                                                            )
                                                        }
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Cancel invitation</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    </div>
                ) : null}

                {permissions.canDeleteOrganisation ? (
                    <div className="space-y-6">
                        <Heading
                            variant="small"
                            title="Schedule organisation deletion"
                            description="Start the 30-day recovery period"
                        />
                        <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                            <div className="relative space-y-0.5 text-red-600 dark:text-red-100">
                                <p className="font-medium">Warning</p>
                                <p className="text-sm">
                                    The organisation remains recoverable for 30
                                    days before deletion.
                                </p>
                            </div>
                            <Button
                                variant="destructive"
                                data-test="delete-organisation-button"
                                onClick={() => setDeleteDialogOpen(true)}
                            >
                                Schedule deletion
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>

            {permissions.canCreateInvitation ? (
                <InviteMemberModal
                    organisation={organisation}
                    availableRoles={availableRoles}
                    programs={programs}
                    personParties={personParties}
                    canOfferOwnership={permissions.canTransferOwnership}
                    open={inviteDialogOpen}
                    onOpenChange={setInviteDialogOpen}
                />
            ) : null}

            <RemoveMemberModal
                organisation={organisation}
                member={memberToRemove}
                open={removeMemberDialogOpen}
                onOpenChange={setRemoveMemberDialogOpen}
            />

            <CancelInvitationModal
                organisation={organisation}
                invitation={invitationToCancel}
                open={cancelInvitationDialogOpen}
                onOpenChange={setCancelInvitationDialogOpen}
            />

            {permissions.canDeleteOrganisation ? (
                <DeleteOrganisationModal
                    organisation={organisation}
                    open={deleteDialogOpen}
                    onOpenChange={setDeleteDialogOpen}
                />
            ) : null}
        </>
    );
}

function MemberRoleAssignments({
    organisation,
    member,
    availableRoles,
    programs,
}: {
    organisation: Organisation;
    member: OrganisationMember;
    availableRoles: RoleOption[];
    programs: ProgramOption[];
}) {
    const [role, setRole] = useState<RoleOption['value']>('case_worker');
    const [programId, setProgramId] = useState('');

    const saveAssignments = (assignments: RoleAssignment[]) => {
        router.visit(updateMember([organisation.slug, member.id]), {
            data: {
                role_assignments: assignments.map((assignment) => ({
                    role: assignment.role,
                    program_id: assignment.program_id,
                })),
            },
            preserveScroll: true,
        });
    };

    const addAssignment = () => {
        const roleOption = availableRoles.find(
            (option) => option.value === role,
        );
        const program = programs.find(
            (option) => option.id === Number(programId),
        );

        saveAssignments([
            ...member.role_assignments,
            {
                role,
                role_label: roleOption?.label ?? role,
                program_id: programId === '' ? null : Number(programId),
                scope_label: program?.name ?? 'Organisation-wide',
            },
        ]);
    };

    return (
        <div className="space-y-3">
            <p className="text-sm font-medium">Operational roles</p>
            <div className="flex flex-wrap gap-2">
                {member.role_assignments.length === 0 ? (
                    <span className="text-muted-foreground text-sm">
                        No operational role
                    </span>
                ) : null}
                {member.role_assignments.map((assignment) => (
                    <Badge
                        key={`${assignment.role}-${assignment.program_id ?? 'organisation'}`}
                        variant="outline"
                        className="gap-1"
                    >
                        {assignment.role_label} · {assignment.scope_label}
                        {member.can_manage_roles ? (
                            <button
                                type="button"
                                aria-label={`Remove ${assignment.role_label} at ${assignment.scope_label}`}
                                onClick={() =>
                                    saveAssignments(
                                        member.role_assignments.filter(
                                            (candidate) =>
                                                candidate.id !== assignment.id,
                                        ),
                                    )
                                }
                            >
                                <X className="size-3" />
                            </button>
                        ) : null}
                    </Badge>
                ))}
            </div>

            {member.can_manage_roles ? (
                <div className="flex flex-wrap gap-2">
                    <select
                        value={role}
                        onChange={(event) => {
                            const nextRole = event.target
                                .value as RoleOption['value'];
                            setRole(nextRole);

                            if (nextRole === 'organisation_administrator') {
                                setProgramId('');
                            }
                        }}
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        {availableRoles.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <select
                        value={programId}
                        disabled={role === 'organisation_administrator'}
                        onChange={(event) => setProgramId(event.target.value)}
                        className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    >
                        <option value="">Organisation-wide</option>
                        {programs.map((program) => (
                            <option key={program.id} value={program.id}>
                                {program.name}
                            </option>
                        ))}
                    </select>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={addAssignment}
                    >
                        <Plus /> Add assignment
                    </Button>
                </div>
            ) : null}
        </div>
    );
}

function MembershipHoldControls({
    organisation,
    member,
}: {
    organisation: Organisation;
    member: OrganisationMember;
}) {
    if (!member.can_manage_hold) {
        return null;
    }

    if (member.hold) {
        return (
            <div className="border-destructive/30 mt-4 flex items-center justify-between rounded-md border p-3">
                <div>
                    <p className="text-sm font-medium">Membership on hold</p>
                    <p className="text-muted-foreground text-xs">
                        {member.hold.reason} · Review{' '}
                        {new Date(member.hold.review_at).toLocaleString()}
                    </p>
                </div>
                <Form
                    {...releaseMembershipHold.form([
                        organisation.slug,
                        member.id,
                        member.hold.id,
                    ])}
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            Release hold
                        </Button>
                    )}
                </Form>
            </div>
        );
    }

    const defaultReviewAt = new Date(Date.now() + 24 * 60 * 60 * 1000)
        .toISOString()
        .slice(0, 16);

    return (
        <Form
            {...createMembershipHold.form([organisation.slug, member.id])}
            className="mt-4 grid gap-2 rounded-md border p-3 sm:grid-cols-[1fr_auto_auto]"
        >
            {({ errors, processing }) => (
                <>
                    <div>
                        <Input
                            name="reason"
                            placeholder="Reason for temporary hold"
                            minLength={10}
                            required
                        />
                        <InputError message={errors.reason} />
                    </div>
                    <div>
                        <Input
                            type="datetime-local"
                            name="review_at"
                            defaultValue={defaultReviewAt}
                            required
                        />
                        <InputError message={errors.review_at} />
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={processing}
                    >
                        Place hold
                    </Button>
                </>
            )}
        </Form>
    );
}

OrganisationEdit.layout = (props: {
    organisation: { name: string; slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Organisations',
            href: index(),
        },
        {
            title: props.organisation.name,
            href: edit(props.organisation.slug),
        },
    ],
});
