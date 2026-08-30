import { Form, Head, router } from '@inertiajs/react';
import { ChevronDown, Mail, ShieldAlert, UserPlus, X } from 'lucide-react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
    store as storeOwnershipTransfer,
    update as acceptOwnershipTransfer,
} from '@/routes/organisations/ownership-transfers';
import { update as updateSlug } from '@/routes/organisations/slug';
import type {
    OrganisationAccessHold,
    OrganisationOwnerCandidate,
    OrganisationOwnershipTransfer,
    ProgramOption,
    RoleOption,
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

    const updateMemberRole = (member: OrganisationMember, newRole: string) => {
        router.visit(updateMember([organisation.slug, member.id]), {
            data: { role: newRole, program_ids: member.program_ids },
            preserveScroll: true,
        });
    };

    const updateMemberProgram = (
        member: OrganisationMember,
        programId: number,
        checked: boolean,
    ) => {
        if (!member.role) {
            return;
        }

        const programIds = checked
            ? [...member.program_ids, programId]
            : member.program_ids.filter((id) => id !== programId);

        router.visit(updateMember([organisation.slug, member.id]), {
            data: { role: member.role, program_ids: programIds },
            preserveScroll: true,
        });
    };

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

                                        {permissions.canUpdateMember ? (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        data-test="member-role-trigger"
                                                    >
                                                        {member.role_label}
                                                        <ChevronDown className="ml-2 h-4 w-4 opacity-50" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    {availableRoles.map(
                                                        (role) => (
                                                            <DropdownMenuItem
                                                                key={role.value}
                                                                data-test="member-role-option"
                                                                onSelect={() =>
                                                                    updateMemberRole(
                                                                        member,
                                                                        role.value,
                                                                    )
                                                                }
                                                            >
                                                                {role.label}
                                                            </DropdownMenuItem>
                                                        ),
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        ) : (
                                            <Badge variant="outline">
                                                {member.role_label}
                                            </Badge>
                                        )}

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

                                {programs.length > 0 ? (
                                    <div className="border-t pt-4">
                                        <p className="mb-3 text-sm font-medium">
                                            Program access
                                        </p>
                                        <div className="flex flex-wrap gap-x-6 gap-y-3">
                                            {programs.map((program) => (
                                                <Label
                                                    key={program.id}
                                                    className="flex items-center gap-2 font-normal"
                                                >
                                                    <Checkbox
                                                        data-test="member-program-checkbox"
                                                        checked={member.program_ids.includes(
                                                            program.id,
                                                        )}
                                                        disabled={
                                                            !permissions.canUpdateMember ||
                                                            !member.role
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            updateMemberProgram(
                                                                member,
                                                                program.id,
                                                                checked ===
                                                                    true,
                                                            )
                                                        }
                                                    />
                                                    {program.name}
                                                </Label>
                                            ))}
                                        </div>
                                        {!member.role ? (
                                            <p className="text-muted-foreground mt-2 text-xs">
                                                Assign an operational role
                                                before granting program access.
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}
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
                                                {invitation.role_label}
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
