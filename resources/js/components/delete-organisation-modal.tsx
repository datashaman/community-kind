import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy } from '@/routes/organisations';
import type { Organisation } from '@/types';

type Props = {
    organisation: Organisation;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteOrganisationModal({
    organisation,
    open,
    onOpenChange,
}: Props) {
    const [confirmationName, setConfirmationName] = useState('');

    const canDeleteOrganisation = confirmationName === organisation.name;

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setConfirmationName('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form(organisation.slug)}
                    className="space-y-6"
                    onSuccess={() => handleOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Schedule organisation deletion?
                                </DialogTitle>
                                <DialogDescription>
                                    This starts a 30-day recovery period for{' '}
                                    <strong>"{organisation.name}"</strong>. The
                                    organisation becomes read-only and can be
                                    restored before the period ends.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="space-y-4 py-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="confirmation-name">
                                        Type{' '}
                                        <strong>"{organisation.name}"</strong>{' '}
                                        to confirm
                                    </Label>
                                    <Input
                                        id="confirmation-name"
                                        name="name"
                                        data-test="delete-organisation-name"
                                        value={confirmationName}
                                        onChange={(event) =>
                                            setConfirmationName(
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Enter organisation name"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    data-test="delete-organisation-confirm"
                                    disabled={
                                        !canDeleteOrganisation || processing
                                    }
                                >
                                    Schedule deletion
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
