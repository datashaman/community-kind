import { Form, Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { index, show, store } from '@/routes/billing-accounts';

type Account = {
    id: string;
    legalName: string;
    payerKind: string;
    status: string;
    role: string;
    isOwner: boolean;
};

export default function BillingAccountsIndex({
    accounts,
}: {
    accounts: Account[];
}) {
    return (
        <div className="space-y-6 p-4">
            <Head title="Billing accounts" />
            <Heading
                title="Billing accounts"
                description="Manage hosted-service payment responsibility separately from Organisation access."
            />
            <Card>
                <CardContent className="pt-6">
                    <Form {...store.form()} className="grid gap-3">
                        <select
                            name="payer_kind"
                            className="h-9 rounded border bg-transparent px-3"
                        >
                            <option value="individual">Individual payer</option>
                            <option value="organisation">
                                Organisation payer
                            </option>
                        </select>
                        <input
                            name="legal_name"
                            required
                            placeholder="Legal payer name"
                            className="h-9 rounded border bg-transparent px-3"
                        />
                        <Button>Create Billing Account</Button>
                    </Form>
                </CardContent>
            </Card>
            <div className="space-y-3">
                {accounts.map((account) => (
                    <Link
                        key={account.id}
                        href={show(account.id)}
                        className="block rounded border p-4"
                    >
                        <strong>{account.legalName}</strong>
                        <p className="text-muted-foreground text-sm">
                            {account.payerKind} · {account.status} ·{' '}
                            {account.isOwner ? 'owner · ' : ''}
                            {account.role}
                        </p>
                    </Link>
                ))}
            </div>
        </div>
    );
}

BillingAccountsIndex.layout = {
    breadcrumbs: [{ title: 'Billing accounts', href: index() }],
};
