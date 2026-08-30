import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { leave as leaveOrganisationAction } from '@/routes/organisations';
import type { Organisation } from '@/types';

type Props = {
    organisation: Organisation | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function LeaveOrganisationModal({
    organisation,
    open,
    onOpenChange,
}: Props) {
    const [processing, setProcessing] = useState(false);

    const leaveOrganisation = () => {
        if (!organisation) {
            return;
        }

        router.visit(leaveOrganisationAction(organisation.slug), {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Leave organisation</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to leave{' '}
                        <strong>{organisation?.name}</strong>?
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Button
                        variant="destructive"
                        data-test="leave-organisation-confirm"
                        disabled={processing}
                        onClick={leaveOrganisation}
                    >
                        Leave organisation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
