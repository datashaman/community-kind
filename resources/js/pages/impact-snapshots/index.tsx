import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { download, index, store } from '@/routes/impact-snapshots';

type Snapshot = {
    id: string;
    audience: string;
    registryVersion: string;
    metricCount: number;
    approvedAt: string;
    publishedAt: string | null;
};

export default function ImpactSnapshotsIndex({
    snapshots,
}: {
    snapshots: Snapshot[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    return (
        <div className="space-y-6 p-4">
            <Head title="Impact packs" />
            <Heading
                title="Impact packs"
                description="Approve an immutable board, funder, or public snapshot from the reconciled metric registry. Active reporting configuration controls which aggregate metrics can leave the dashboard."
            />
            <Card>
                <CardHeader>
                    <CardTitle>Approve snapshot</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        {...store.form(organisation.slug)}
                        className="grid gap-3 md:grid-cols-3"
                    >
                        <select
                            name="audience"
                            className="h-9 rounded border bg-transparent px-3"
                        >
                            <option value="board">Board pack</option>
                            <option value="funder">Funder pack</option>
                            <option value="public">Public impact</option>
                        </select>
                        <input
                            name="period_start"
                            type="date"
                            className="h-9 rounded border bg-transparent px-3"
                        />
                        <input
                            name="period_end"
                            type="date"
                            className="h-9 rounded border bg-transparent px-3"
                        />
                        <Button className="md:col-span-3">
                            Approve reconciled snapshot
                        </Button>
                    </Form>
                </CardContent>
            </Card>
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Approved snapshots</h2>
                {snapshots.map((snapshot) => (
                    <Card key={snapshot.id}>
                        <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                            <div>
                                <strong>{snapshot.audience} impact</strong>
                                <p className="text-muted-foreground text-sm">
                                    Registry {snapshot.registryVersion} ·{' '}
                                    {snapshot.metricCount} metrics ·{' '}
                                    {new Date(
                                        snapshot.approvedAt,
                                    ).toLocaleString()}
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <Badge>
                                    {snapshot.publishedAt
                                        ? 'published'
                                        : 'approved'}
                                </Badge>
                                {snapshot.audience !== 'public' ? (
                                    <Button variant="outline" asChild>
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
                ))}
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
