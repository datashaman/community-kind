import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { accept } from '@/routes/billing-invitations';

export default function AcceptBillingInvitation({
    token,
    account,
    role,
    offersOwnership,
}: {
    token: string;
    account: { legalName: string; payerKind: string };
    role: string;
    offersOwnership: boolean;
}) {
    return (
        <div className="mx-auto max-w-xl space-y-6 p-6">
            <Head title="Accept Billing Account invitation" />
            <Heading
                title={`Join ${account.legalName}`}
                description={`Accept ${role} billing authority${offersOwnership ? ' and Owner responsibility' : ''}. This grants no Organisation access.`}
            />
            <Form {...accept.form(token)}>
                <Button>Accept Billing Account invitation</Button>
            </Form>
        </div>
    );
}
