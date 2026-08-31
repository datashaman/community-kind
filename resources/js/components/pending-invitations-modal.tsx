import { router } from '@inertiajs/react';
import { useState } from 'react';
import OrganisationInvitationController from '@/actions/App/Http/Controllers/Organisations/OrganisationInvitationController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { DashboardInvitation } from '@/types';

type Props = {
    invitations: DashboardInvitation[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function PendingInvitationsModal({
    invitations,
    open,
    onOpenChange,
}: Props) {
    const [processingId, setProcessingId] = useState<number | null>(null);

    const acceptInvitation = (invitation: DashboardInvitation) => {
        router.visit(OrganisationInvitationController.accept(invitation), {
            onStart: () => setProcessingId(invitation.id),
            onFinish: () => setProcessingId(null),
        });
    };

    const declineInvitation = (invitation: DashboardInvitation) => {
        router.visit(OrganisationInvitationController.decline(invitation), {
            onStart: () => setProcessingId(invitation.id),
            onFinish: () => setProcessingId(null),
            onSuccess: () => {
                if (invitations.length === 1) {
                    onOpenChange(false);
                }
            },
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent data-test="pending-invitations-modal">
                <DialogHeader>
                    <DialogTitle>Pending organisation invitations</DialogTitle>
                    <DialogDescription>
                        Accept or decline the organisations you have been
                        invited to join.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    {invitations.map((invitation) => (
                        <div
                            key={invitation.id}
                            data-test="pending-invitation-row"
                            className="rounded-lg border p-4"
                        >
                            <div className="space-y-1">
                                <p className="font-medium">
                                    {invitation.organisation.name}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {invitation.inviterName} invited you to join
                                    this organisation as {invitation.personName}
                                    .
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {invitation.roleAssignments
                                        .map(
                                            (assignment) =>
                                                `${assignment.roleLabel} (${assignment.scopeLabel})`,
                                        )
                                        .join(', ')}
                                </p>
                                {invitation.offersOwnership ? (
                                    <p className="text-sm font-medium">
                                        Accepting also accepts Organisation
                                        Owner responsibility.
                                    </p>
                                ) : null}
                            </div>

                            <div className="mt-4 flex justify-end gap-2">
                                <Button
                                    variant="secondary"
                                    data-test="pending-invitation-decline"
                                    disabled={processingId === invitation.id}
                                    onClick={() =>
                                        declineInvitation(invitation)
                                    }
                                >
                                    Decline
                                </Button>

                                <Button
                                    data-test="pending-invitation-accept"
                                    disabled={processingId === invitation.id}
                                    onClick={() => acceptInvitation(invitation)}
                                >
                                    Accept
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </DialogContent>
        </Dialog>
    );
}
