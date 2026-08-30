import { Form } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeInvitation } from '@/routes/organisations/invitations';
import { Checkbox } from '@/components/ui/checkbox';
import type {
    Organisation,
    PersonPartyOption,
    ProgramOption,
    RoleOption,
} from '@/types';

type Props = {
    organisation: Organisation;
    availableRoles: RoleOption[];
    programs: ProgramOption[];
    personParties: PersonPartyOption[];
    canOfferOwnership: boolean;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function InviteMemberModal({
    organisation,
    availableRoles,
    programs,
    personParties,
    canOfferOwnership,
    open,
    onOpenChange,
}: Props) {
    const [partyChoice, setPartyChoice] = useState('new');
    const [assignments, setAssignments] = useState([
        { role: 'case_worker', programId: '' },
    ]);

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setPartyChoice('new');
            setAssignments([{ role: 'case_worker', programId: '' }]);
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...storeInvitation.form(organisation.slug)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>
                                    Invite an organisation member
                                </DialogTitle>
                                <DialogDescription>
                                    Send an invitation to join this
                                    organisation.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        data-test="invite-email"
                                        placeholder="colleague@example.com"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="person_party_id">
                                        Person Party
                                    </Label>
                                    <Select
                                        value={partyChoice}
                                        onValueChange={setPartyChoice}
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="new">
                                                Create a new person Party
                                            </SelectItem>
                                            {personParties.map((party) => (
                                                <SelectItem
                                                    key={party.id}
                                                    value={String(party.id)}
                                                >
                                                    {party.display_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {partyChoice === 'new' ? (
                                        <Input
                                            name="new_person_name"
                                            placeholder="Person's full name"
                                            required
                                        />
                                    ) : (
                                        <input
                                            type="hidden"
                                            name="person_party_id"
                                            value={partyChoice}
                                        />
                                    )}
                                    <InputError
                                        message={
                                            errors.person_party_id ??
                                            errors.new_person_name
                                        }
                                    />
                                </div>

                                <div className="grid gap-3">
                                    <div className="flex items-center justify-between">
                                        <Label>Initial role assignments</Label>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setAssignments((current) => [
                                                    ...current,
                                                    {
                                                        role: 'case_worker',
                                                        programId: '',
                                                    },
                                                ])
                                            }
                                        >
                                            <Plus /> Add role
                                        </Button>
                                    </div>
                                    {assignments.map((assignment, index) => (
                                        <div
                                            key={index}
                                            className="grid grid-cols-[1fr_1fr_auto] gap-2"
                                        >
                                            <select
                                                name={`role_assignments[${index}][role]`}
                                                value={assignment.role}
                                                onChange={(event) =>
                                                    setAssignments((current) =>
                                                        current.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          role: event
                                                                              .target
                                                                              .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                            >
                                                {availableRoles.map((role) => (
                                                    <option
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <select
                                                name={`role_assignments[${index}][program_id]`}
                                                value={assignment.programId}
                                                disabled={
                                                    assignment.role ===
                                                    'organisation_administrator'
                                                }
                                                onChange={(event) =>
                                                    setAssignments((current) =>
                                                        current.map(
                                                            (
                                                                item,
                                                                itemIndex,
                                                            ) =>
                                                                itemIndex ===
                                                                index
                                                                    ? {
                                                                          ...item,
                                                                          programId:
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                      }
                                                                    : item,
                                                        ),
                                                    )
                                                }
                                                className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                            >
                                                <option value="">
                                                    Organisation-wide
                                                </option>
                                                {programs.map((program) => (
                                                    <option
                                                        key={program.id}
                                                        value={program.id}
                                                    >
                                                        {program.name}
                                                    </option>
                                                ))}
                                            </select>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                disabled={
                                                    assignments.length === 1
                                                }
                                                onClick={() =>
                                                    setAssignments((current) =>
                                                        current.filter(
                                                            (_, itemIndex) =>
                                                                itemIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <X />
                                            </Button>
                                        </div>
                                    ))}
                                    <InputError
                                        message={errors.role_assignments}
                                    />
                                </div>

                                {canOfferOwnership ? (
                                    <Label className="flex items-center gap-2 font-normal">
                                        <Checkbox
                                            name="offers_ownership"
                                            value="1"
                                        />
                                        Offer Organisation Owner responsibility
                                    </Label>
                                ) : null}
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="invite-submit"
                                    disabled={processing}
                                >
                                    Send invitation
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
