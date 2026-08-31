import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { index, show } from '@/routes/donations';

type DonationSummary = {
    id: string;
    supporter: string;
    campaign: string | null;
    fund: string;
    frequency: string;
    amountMinor: number;
    currency: string;
    source: string;
    mandateStatus: string | null;
    paymentCount: number;
    createdAt: string | null;
};

type Props = {
    donations: {
        data: DonationSummary[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
};

const money = (currency: string, amountMinor: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(amountMinor / 100);

export default function DonationsIndex({ donations }: Props) {
    const organisation = usePage().props.currentOrganisation!;

    return (
        <>
            <Head title="Simulated donations" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Simulated donations"
                    description="Demo-only fundraising intent, payment attempts, mandates, refunds, receipts, and attribution. No real money or contact data is used."
                />
                <div
                    role="note"
                    className="rounded-lg border-2 border-amber-600 p-4 text-sm"
                >
                    <strong>Simulation data only.</strong> Provider identifiers
                    begin with sim_ and every receipt is marked as a non-tax
                    demo receipt.
                </div>
                {donations.data.length === 0 ? (
                    <Card>
                        <CardContent className="text-muted-foreground p-6">
                            No simulated donations yet.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-3">
                        {donations.data.map((donation) => (
                            <Link
                                key={donation.id}
                                href={show.url([
                                    organisation.slug,
                                    donation.id,
                                ])}
                                className="hover:bg-muted/50 focus-visible:ring-ring rounded-lg border p-4 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold">
                                            {donation.supporter}
                                        </p>
                                        <p className="text-muted-foreground text-sm">
                                            {donation.campaign ?? 'No campaign'}{' '}
                                            · {donation.fund} ·{' '}
                                            {donation.source}
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-semibold">
                                            {money(
                                                donation.currency,
                                                donation.amountMinor,
                                            )}
                                        </p>
                                        <div className="mt-1 flex gap-1">
                                            <Badge variant="outline">
                                                {donation.frequency.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </Badge>
                                            {donation.mandateStatus ? (
                                                <Badge variant="outline">
                                                    {donation.mandateStatus.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </Badge>
                                            ) : null}
                                        </div>
                                    </div>
                                </div>
                                <p className="text-muted-foreground mt-3 text-xs">
                                    {donation.paymentCount} simulated payment
                                    attempt(s) · transaction {donation.id}
                                </p>
                            </Link>
                        ))}
                    </div>
                )}
                <nav aria-label="Donation pages" className="flex gap-2">
                    {donations.links.map((link) =>
                        link.url ? (
                            <Link
                                key={link.label}
                                href={link.url}
                                className="rounded border px-3 py-1 text-sm"
                                aria-current={link.active ? 'page' : undefined}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : null,
                    )}
                </nav>
            </div>
        </>
    );
}

DonationsIndex.layout = (props: { currentOrganisation: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Simulated donations',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
