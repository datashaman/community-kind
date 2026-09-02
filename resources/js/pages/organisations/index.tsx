import { Head, Link, usePage } from '@inertiajs/react';
import { Eye, LogOut, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import CreateOrganisationModal from '@/components/create-organisation-modal';
import Heading from '@/components/heading';
import LeaveOrganisationModal from '@/components/leave-organisation-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit, index } from '@/routes/organisations';
import type { Organisation } from '@/types';

type Props = {
    organisations: Organisation[];
};

export default function OrganisationsIndex({ organisations }: Props) {
    const canCreateOrganisation = usePage().props.canCreateOrganisation;
    const [leaveOrganisationDialogOpen, setLeaveOrganisationDialogOpen] =
        useState(false);
    const [organisationLeaving, setOrganisationLeaving] =
        useState<Organisation | null>(null);

    const openLeaveOrganisationDialog = (organisation: Organisation) => {
        setOrganisationLeaving(organisation);
        setLeaveOrganisationDialogOpen(true);
    };

    return (
        <>
            <Head title="Organisations" />

            <div className="flex flex-col space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        variant="small"
                        title="Organisations"
                        description="Manage your organisations and organisation memberships"
                    />

                    {canCreateOrganisation ? (
                        <CreateOrganisationModal>
                            <Button data-test="organisations-new-organisation-button">
                                <Plus /> New organisation
                            </Button>
                        </CreateOrganisationModal>
                    ) : null}
                </div>

                <div className="space-y-3">
                    {organisations.map((organisation) => {
                        const canLeaveOrganisation = !organisation.isOwner;
                        const canEditOrganisation =
                            organisation.isOwner ||
                            organisation.role === 'organisation_administrator';

                        return (
                            <div
                                key={organisation.id}
                                data-test="organisation-row"
                                className="flex items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="flex items-center gap-4">
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">
                                                {organisation.name}
                                            </span>
                                            {organisation.status ? (
                                                <Badge variant="outline">
                                                    {organisation.status.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <span className="text-muted-foreground text-sm">
                                            {organisation.roleLabel}
                                        </span>
                                    </div>
                                </div>

                                <TooltipProvider>
                                    <div className="flex items-center gap-2">
                                        {canLeaveOrganisation ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="organisation-leave-button"
                                                        onClick={() =>
                                                            openLeaveOrganisationDialog(
                                                                organisation,
                                                            )
                                                        }
                                                    >
                                                        <LogOut className="h-4 w-4" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Leave organisation</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : null}

                                        {!canEditOrganisation ? (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="organisation-view-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                organisation.slug,
                                                            )}
                                                        >
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>View organisation</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        ) : (
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        data-test="organisation-edit-button"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={edit(
                                                                organisation.slug,
                                                            )}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Edit organisation</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        )}
                                    </div>
                                </TooltipProvider>
                            </div>
                        );
                    })}

                    {organisations.length === 0 ? (
                        <p className="text-muted-foreground py-8 text-center">
                            You don't belong to any organisations yet.
                        </p>
                    ) : null}
                </div>
            </div>

            <LeaveOrganisationModal
                organisation={organisationLeaving}
                open={leaveOrganisationDialogOpen}
                onOpenChange={setLeaveOrganisationDialogOpen}
            />
        </>
    );
}

OrganisationsIndex.layout = {
    breadcrumbs: [
        {
            title: 'Organisations',
            href: index(),
        },
    ],
};
