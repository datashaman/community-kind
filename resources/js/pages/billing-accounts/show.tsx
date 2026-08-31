import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { destroy } from '@/routes/billing-accounts';
import {
    destroy as removeContact,
    store as storeContact,
} from '@/routes/billing-accounts/contacts';
import { store as storeInvitation } from '@/routes/billing-accounts/invitations';
import {
    destroy as leaveMembership,
    update as updateMembership,
} from '@/routes/billing-accounts/memberships';

type Member = {
    id: string;
    name: string;
    email: string;
    role: string;
    isOwner: boolean;
};
type Props = {
    account: {
        id: string;
        legalName: string;
        payerKind: string;
        status: string;
    };
    membership: { id: string; role: string; isOwner: boolean };
    members: Member[];
    contacts: Array<{
        id: string;
        name: string;
        email: string;
        purposes: string[];
    }>;
    invitations: Array<{
        id: string;
        email: string;
        role: string;
        offers_ownership: boolean;
    }>;
    currentSubscriptionCount: number;
};

export default function BillingAccountShow({
    account,
    membership,
    members,
    contacts,
    invitations,
    currentSubscriptionCount,
}: Props) {
    const canAdminister =
        membership.role === 'administrator' && account.status === 'open';
    const canInvite =
        (canAdminister || membership.isOwner) && account.status === 'open';
    return (
        <div className="space-y-6 p-4">
            <Head title={account.legalName} />
            <Heading
                title={account.legalName}
                description={`${account.payerKind} payer · ${account.status}. Billing authority grants no Organisation access.`}
            />
            <Card>
                <CardHeader>
                    <CardTitle>Accepted members</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    {members.map((member) => (
                        <div key={member.id} className="rounded border p-3">
                            <strong>{member.name}</strong>
                            <p className="text-sm">
                                {member.email} ·{' '}
                                {member.isOwner ? 'owner · ' : ''}
                                {member.role}
                            </p>
                            {membership.isOwner ? (
                                <div className="mt-2 flex flex-wrap gap-2">
                                    <Form
                                        {...updateMembership.form([
                                            account.id,
                                            member.id,
                                        ])}
                                        className="flex gap-2"
                                    >
                                        <select
                                            name="role"
                                            defaultValue={member.role}
                                            className="rounded border bg-transparent px-2"
                                        >
                                            <option value="administrator">
                                                Administrator
                                            </option>
                                            <option value="viewer">
                                                Viewer
                                            </option>
                                        </select>
                                        <input
                                            type="hidden"
                                            name="is_owner"
                                            value="0"
                                        />
                                        <label>
                                            <input
                                                type="checkbox"
                                                name="is_owner"
                                                value="1"
                                                defaultChecked={member.isOwner}
                                            />{' '}
                                            Owner
                                        </label>
                                        <Button size="sm">Update</Button>
                                    </Form>
                                    {member.id !== membership.id ? (
                                        <Form
                                            {...leaveMembership.form([
                                                account.id,
                                                member.id,
                                            ])}
                                        >
                                            <Button size="sm" variant="outline">
                                                End Membership
                                            </Button>
                                        </Form>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>
                    ))}
                </CardContent>
            </Card>
            {canInvite ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Invite member</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...storeInvitation.form(account.id)}
                            className="grid gap-2"
                        >
                            <input
                                name="email"
                                type="email"
                                required
                                placeholder="Email"
                                className="h-9 rounded border px-3"
                            />
                            <select name="role">
                                <option value="viewer">Viewer</option>
                                <option value="administrator">
                                    Administrator
                                </option>
                            </select>
                            <input
                                type="hidden"
                                name="offers_ownership"
                                value="0"
                            />
                            {membership.isOwner ? (
                                <label>
                                    <input
                                        type="checkbox"
                                        name="offers_ownership"
                                        value="1"
                                    />{' '}
                                    Offer Owner responsibility
                                </label>
                            ) : null}
                            <Button>Issue invitation</Button>
                        </Form>
                        <p className="mt-2 text-sm">
                            Pending:{' '}
                            {invitations.map((i) => i.email).join(', ') ||
                                'none'}
                        </p>
                    </CardContent>
                </Card>
            ) : null}
            {canAdminister ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Billing contacts</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...storeContact.form(account.id)}
                            className="grid gap-2"
                        >
                            <input
                                name="name"
                                required
                                placeholder="Contact name"
                            />
                            <input
                                name="email"
                                type="email"
                                required
                                placeholder="Contact email"
                            />
                            <label>
                                <input
                                    type="checkbox"
                                    name="purposes[]"
                                    value="invoice"
                                    defaultChecked
                                />{' '}
                                Invoices
                            </label>
                            <label>
                                <input
                                    type="checkbox"
                                    name="purposes[]"
                                    value="renewal"
                                />{' '}
                                Renewals
                            </label>
                            <Button>Add contact</Button>
                        </Form>
                        <div className="mt-3 space-y-2">
                            {contacts.map((contact) => (
                                <div
                                    key={contact.id}
                                    className="flex items-center justify-between text-sm"
                                >
                                    <span>
                                        {contact.name} (
                                        {contact.purposes.join(', ')})
                                    </span>
                                    <Form
                                        {...removeContact.form([
                                            account.id,
                                            contact.id,
                                        ])}
                                    >
                                        <Button size="sm" variant="ghost">
                                            Remove
                                        </Button>
                                    </Form>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            ) : null}
            <p>{currentSubscriptionCount} current subscription(s).</p>
            {membership.isOwner && account.status === 'open' ? (
                <Form {...destroy.form(account.id)}>
                    <Button
                        variant="destructive"
                        disabled={currentSubscriptionCount > 0}
                    >
                        Close Billing Account
                    </Button>
                </Form>
            ) : null}
            {!membership.isOwner ? (
                <Form {...leaveMembership.form([account.id, membership.id])}>
                    <Button variant="outline">Leave Billing Account</Button>
                </Form>
            ) : null}
        </div>
    );
}
