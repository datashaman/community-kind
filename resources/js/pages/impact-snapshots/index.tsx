import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { download, index, store } from '@/routes/impact-snapshots';
import { index as reportingPublication } from '@/routes/reporting-publication';

type Snapshot = {
    id: string;
    audience: string;
    registryVersion: string;
    metricCount: number;
    approvedAt: string;
    publishedAt: string | null;
};

const fieldClass = 'h-9 rounded-md border bg-transparent px-3';

function ApprovalPanel({ onDismiss }: { onDismiss: () => void }) {
    const organisation = usePage().props.currentOrganisation!;
    const firstField = useRef<HTMLSelectElement>(null);

    /*
     * The panel is not on the page until it is asked for, so opening it has to
     * take the caret with it. Without this the keyboard user is left at the
     * button and has to tab back into a region that appeared behind them.
     */
    useEffect(() => firstField.current?.focus(), []);

    return (
        <section
            aria-labelledby="approve-snapshot-heading"
            className="bg-muted/30 space-y-4 rounded-xl border p-5"
        >
            <h2 id="approve-snapshot-heading" className="font-medium">
                Approve a snapshot
            </h2>
            <Form
                {...store.form(organisation.slug)}
                onSuccess={onDismiss}
                className="grid items-end gap-4 md:grid-cols-3"
            >
                <div className="grid gap-1.5">
                    <Label htmlFor="audience">Audience</Label>
                    <select
                        id="audience"
                        name="audience"
                        ref={firstField}
                        className={fieldClass}
                    >
                        <option value="board">Board pack</option>
                        <option value="funder">Funder pack</option>
                        <option value="public">Public impact</option>
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="period-start">Period start</Label>
                    <input
                        id="period-start"
                        name="period_start"
                        type="date"
                        className={fieldClass}
                    />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="period-end">Period end</Label>
                    <input
                        id="period-end"
                        name="period_end"
                        type="date"
                        className={fieldClass}
                    />
                </div>
                <div className="flex gap-2 md:col-span-3">
                    <Button>Approve reconciled snapshot</Button>
                    <Button type="button" variant="ghost" onClick={onDismiss}>
                        Cancel
                    </Button>
                </div>
            </Form>
            <p className="text-muted-foreground text-sm">
                An approved snapshot is immutable. The active reporting
                configuration decides which aggregate metrics may leave the
                dashboard.
            </p>
        </section>
    );
}

export default function ImpactSnapshotsIndex({
    snapshots,
    canApprove,
    canConfigureReporting,
}: {
    snapshots: Snapshot[];
    canApprove: boolean;
    canConfigureReporting: boolean;
}) {
    const organisation = usePage().props.currentOrganisation!;
    const [approving, setApproving] = useState(false);
    const approveButton = useRef<HTMLButtonElement>(null);
    const returnFocus = useRef(false);

    /*
     * The button is disabled while the panel is open, so it cannot take focus
     * until the panel has gone and this render has landed. Restoring focus
     * inside the dismiss handler silently does nothing.
     */
    useEffect(() => {
        if (approving || !returnFocus.current) return;
        returnFocus.current = false;
        approveButton.current?.focus();
    }, [approving]);

    const dismiss = () => {
        returnFocus.current = true;
        setApproving(false);
    };

    return (
        <div className="space-y-6 p-4">
            <Head title="Impact packs" />
            <div className="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Impact packs"
                    description="Immutable board, funder, and public snapshots approved from the reconciled metric registry."
                />
                {canApprove ? (
                    <Button
                        ref={approveButton}
                        type="button"
                        onClick={() => setApproving(true)}
                        disabled={approving}
                    >
                        Approve snapshot
                    </Button>
                ) : null}
            </div>

            {/*
             * Approving needs an active impact reporting configuration to say
             * which metrics may leave the dashboard. The page used to offer the
             * action anyway and fail on submit with a 500.
             */}
            {canApprove ? null : (
                <div className="bg-muted/30 rounded-xl border p-5">
                    <p className="font-medium">
                        No reporting configuration is active
                    </p>
                    <p className="text-muted-foreground mt-1 text-sm">
                        A snapshot can only carry metrics an active reporting
                        configuration has approved for release.
                        {canConfigureReporting
                            ? ' Activate one, then come back to approve a snapshot.'
                            : ' An organisation administrator activates one under Reporting publication.'}
                    </p>
                    {/*
                     * Reporting publication needs OrganisationAdministrator,
                     * which the Executive Viewer approving snapshots does not
                     * have. Offering the link regardless would replace a failing
                     * button with a link to a 403.
                     */}
                    {canConfigureReporting ? (
                        <Button asChild variant="outline" className="mt-3">
                            <Link
                                href={reportingPublication(organisation.slug)}
                            >
                                Open Reporting publication
                            </Link>
                        </Button>
                    ) : null}
                </div>
            )}

            {approving ? <ApprovalPanel onDismiss={dismiss} /> : null}

            <section aria-labelledby="approved-snapshots-heading">
                <h2
                    id="approved-snapshots-heading"
                    className="mb-3 text-xl font-semibold"
                >
                    Approved snapshots
                </h2>
                {snapshots.length === 0 ? (
                    <div className="bg-muted/30 rounded-xl border border-dashed p-6">
                        <p className="font-medium">No snapshots approved yet</p>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Approving a snapshot freezes the reconciled metrics
                            for a period so a board or funder can be given a
                            figure that will not move under them.
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {snapshots.map((snapshot) => (
                            <li key={snapshot.id}>
                                <Card>
                                    <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                                        <div>
                                            <strong>
                                                {snapshot.audience} impact
                                            </strong>
                                            <p className="text-muted-foreground text-sm">
                                                Registry{' '}
                                                {snapshot.registryVersion} ·{' '}
                                                {snapshot.metricCount} metrics ·{' '}
                                                <time
                                                    dateTime={
                                                        snapshot.approvedAt
                                                    }
                                                >
                                                    {new Date(
                                                        snapshot.approvedAt,
                                                    ).toLocaleString()}
                                                </time>
                                            </p>
                                        </div>
                                        <div className="flex gap-2">
                                            <Badge>
                                                {snapshot.publishedAt
                                                    ? 'published'
                                                    : 'approved'}
                                            </Badge>
                                            {snapshot.audience !== 'public' ? (
                                                <Button
                                                    variant="outline"
                                                    asChild
                                                >
                                                    <a
                                                        href={
                                                            download([
                                                                organisation.slug,
                                                                snapshot.id,
                                                            ]).url
                                                        }
                                                    >
                                                        Download CSV pack
                                                    </a>
                                                </Button>
                                            ) : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

ImpactSnapshotsIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        { title: 'Impact packs', href: index(props.currentOrganisation.slug) },
    ],
});
