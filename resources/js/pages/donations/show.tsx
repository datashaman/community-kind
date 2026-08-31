import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, show } from '@/routes/donations';

type Transition = { from: string; to: string; occurredAt: string };
type Payment = {
    id: string;
    attemptNumber: number;
    status: string;
    providerId: string;
    amountMinor: number;
    currency: string;
    settledAt: string | null;
    events: Transition[];
    refunds: Array<{ id: string; amountMinor: number; occurredAt: string }>;
    receipt: { number: string; marker: string; issuedAt: string } | null;
};
type DonationDetail = {
    id: string;
    supporter: string;
    campaign: string | null;
    fund: string;
    frequency: string;
    amountMinor: number;
    currency: string;
    source: string;
    createdAt: string | null;
    mandate: {
        id: string;
        status: string;
        providerId: string;
        events: Transition[];
    } | null;
    payments: Payment[];
};

const money = (currency: string, amountMinor: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(amountMinor / 100);

export default function DonationShow({
    donation,
}: {
    donation: DonationDetail;
}) {
    return (
        <>
            <Head title="Simulated donation" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Simulated donation"
                    description={`Transaction ${donation.id} · demo data only; no money moved and no real person was contacted.`}
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Intent and attribution</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 sm:grid-cols-2">
                        <Detail
                            label="Synthetic supporter"
                            value={donation.supporter}
                        />
                        <Detail
                            label="Demo amount"
                            value={money(
                                donation.currency,
                                donation.amountMinor,
                            )}
                        />
                        <Detail label="Frequency" value={donation.frequency} />
                        <Detail label="Fund" value={donation.fund} />
                        <Detail
                            label="Campaign"
                            value={donation.campaign ?? 'None'}
                        />
                        <Detail label="Source" value={donation.source} />
                    </CardContent>
                </Card>
                {donation.mandate ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Simulated recurring mandate</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <Badge>{donation.mandate.status}</Badge>
                            <p className="font-mono text-xs">
                                {donation.mandate.providerId}
                            </p>
                            <Transitions events={donation.mandate.events} />
                        </CardContent>
                    </Card>
                ) : null}
                <section
                    aria-labelledby="payment-attempts"
                    className="space-y-3"
                >
                    <h2 id="payment-attempts" className="text-xl font-semibold">
                        Simulated payment attempts
                    </h2>
                    {donation.payments.map((payment) => (
                        <Card key={payment.id}>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between gap-2">
                                    Attempt {payment.attemptNumber}
                                    <Badge>{payment.status}</Badge>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="font-mono text-xs">
                                    {payment.providerId}
                                </p>
                                <Transitions events={payment.events} />
                                {payment.refunds.map((refund) => (
                                    <p key={refund.id}>
                                        Demo refund:{' '}
                                        {money(
                                            payment.currency,
                                            refund.amountMinor,
                                        )}
                                    </p>
                                ))}
                                {payment.receipt ? (
                                    <div className="rounded border-2 border-dashed border-amber-600 p-4">
                                        <strong>
                                            {payment.receipt.marker}
                                        </strong>
                                        <p>Receipt {payment.receipt.number}</p>
                                    </div>
                                ) : (
                                    <p className="text-muted-foreground text-sm">
                                        No receipt: this attempt has no eligible
                                        settled value.
                                    </p>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </section>
            </div>
        </>
    );
}

function Detail({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <p className="text-muted-foreground text-sm">{label}</p>
            <p>{value.replaceAll('_', ' ')}</p>
        </div>
    );
}

function Transitions({ events }: { events: Transition[] }) {
    return events.length === 0 ? (
        <p className="text-muted-foreground text-sm">No transitions.</p>
    ) : (
        <ol className="space-y-1 text-sm" aria-label="Transition history">
            {events.map((event, indexNumber) => (
                <li key={`${event.occurredAt}-${indexNumber}`}>
                    {event.from.replaceAll('_', ' ')} →{' '}
                    {event.to.replaceAll('_', ' ')}
                </li>
            ))}
        </ol>
    );
}

DonationShow.layout = (props: {
    currentOrganisation: { slug: string };
    donation: DonationDetail;
}) => ({
    breadcrumbs: [
        {
            title: 'Simulated donations',
            href: index(props.currentOrganisation.slug),
        },
        {
            title: 'Transaction',
            href: show([props.currentOrganisation.slug, props.donation.id]),
        },
    ],
});
