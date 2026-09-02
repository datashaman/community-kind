import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { Check, Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { activate, index, store } from '@/routes/reporting-publication';

type Metric = {
    id: string;
    version: string;
    category: string;
    domain: string;
    label: string;
    description: string;
    formula: string;
    unit: string;
    dimensions: string[];
};

type SelectedMetric = {
    id: string;
    label: string;
    domain: string | null;
    unit: string | null;
    available: boolean;
};

type ReportingVersion = {
    id: string;
    version: number;
    status: string;
    publicMetrics: SelectedMetric[];
    packMetrics: SelectedMetric[];
    activatedAt: string | null;
    hasUnavailableMetrics: boolean;
    canActivate: boolean;
};

const PUBLIC_LABEL = 'Public impact page';
const PACK_LABEL = 'Approved reporting packs';

/*
 * One row per metric, one column per destination. Listing each destination
 * separately meant every metric's label, badges, and description appeared
 * twice, and divergence between the two selections could only be found by
 * reading both lists side by side.
 */
function DestinationCell({
    included,
    metric,
    destination,
}: {
    included: boolean;
    metric: string;
    destination: string;
}) {
    return (
        <td className="px-4 py-3 text-center align-top">
            {included ? (
                <Check className="mx-auto size-4" aria-hidden="true" />
            ) : (
                <span className="text-muted-foreground" aria-hidden="true">
                    —
                </span>
            )}
            <span className="sr-only">
                {metric}
                {included ? ' is published on ' : ' is not published on '}
                {destination}
            </span>
        </td>
    );
}

function MetricToggleCell({
    checked,
    onChange,
    label,
}: {
    checked: boolean;
    onChange: (checked: boolean) => void;
    label: string;
}) {
    return (
        <td className="p-0 align-top">
            {/*
             * A bare `size-4` checkbox is a 16px target centred in a column
             * five times its width, so almost every click in the cell lands on
             * nothing. WCAG 2.2 AA 2.5.8 wants 24px; this gives the whole
             * padded cell.
             *
             * The hit area is a pseudo-element on the checkbox rather than a
             * wrapping label, so there is exactly one target and no question
             * of a label forwarding a second click to the control inside it.
             */}
            <div className="hover:bg-muted/60 relative flex justify-center px-4 py-3">
                <Checkbox
                    className="after:absolute after:inset-0 after:cursor-pointer"
                    checked={checked}
                    onCheckedChange={(state) => onChange(state === true)}
                    aria-label={label}
                />
            </div>
        </td>
    );
}

function VersionMetricMatrix({ version }: { version: ReportingVersion }) {
    const rows = new Map<
        string,
        { metric: SelectedMetric; inPublic: boolean; inPack: boolean }
    >();

    for (const metric of version.publicMetrics) {
        rows.set(metric.id, { metric, inPublic: true, inPack: false });
    }

    for (const metric of version.packMetrics) {
        const existing = rows.get(metric.id);

        if (existing) {
            existing.inPack = true;
        } else {
            rows.set(metric.id, { metric, inPublic: false, inPack: true });
        }
    }

    if (rows.size === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                No metrics selected in this version.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto rounded-lg border">
            <table className="w-full text-left text-sm">
                <caption className="sr-only">
                    Metrics published by reporting publication version{' '}
                    {version.version}, and the destination of each
                </caption>
                <thead className="bg-muted/50">
                    <tr>
                        <th scope="col" className="px-4 py-2 font-medium">
                            Metric
                        </th>
                        <th
                            scope="col"
                            className="w-28 px-4 py-2 text-center font-medium"
                        >
                            Public page
                        </th>
                        <th
                            scope="col"
                            className="w-28 px-4 py-2 text-center font-medium"
                        >
                            Reporting pack
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {[...rows.values()].map(({ metric, inPublic, inPack }) => (
                        <tr key={metric.id}>
                            <th
                                scope="row"
                                className="px-4 py-3 text-left font-normal"
                            >
                                <span className="flex flex-wrap items-center gap-2">
                                    {metric.label}
                                    {metric.available ? null : (
                                        <Badge variant="destructive">
                                            Retired or unavailable
                                        </Badge>
                                    )}
                                </span>
                            </th>
                            <DestinationCell
                                included={inPublic}
                                metric={metric.label}
                                destination={PUBLIC_LABEL}
                            />
                            <DestinationCell
                                included={inPack}
                                metric={metric.label}
                                destination={PACK_LABEL}
                            />
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function ReportingPublicationIndex({
    metrics,
    versions,
}: {
    metrics: Metric[];
    versions: ReportingVersion[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    const [query, setQuery] = useState('');
    const editor = useForm({
        public_metric_ids: [] as string[],
        pack_metric_ids: [] as string[],
    });
    const normalizedQuery = query.trim().toLowerCase();
    const filteredMetrics = metrics.filter((metric) =>
        [
            metric.label,
            metric.id,
            metric.description,
            metric.domain,
            metric.category,
        ].some((value) => value.toLowerCase().includes(normalizedQuery)),
    );

    const visibleIds = new Set(filteredMetrics.map((metric) => metric.id));
    const selectedIds = new Set([
        ...editor.data.public_metric_ids,
        ...editor.data.pack_metric_ids,
    ]);
    const hiddenSelectedCount = [...selectedIds].filter(
        (id) => !visibleIds.has(id),
    ).length;

    const toggle = (
        field: 'public_metric_ids' | 'pack_metric_ids',
        id: string,
        checked: boolean,
    ) => {
        editor.setData(
            field,
            checked
                ? [...editor.data[field], id]
                : editor.data[field].filter((candidate) => candidate !== id),
        );
    };

    const useAsStartingPoint = (version: ReportingVersion) => {
        editor.setData({
            public_metric_ids: version.publicMetrics
                .filter((metric) => metric.available)
                .map((metric) => metric.id),
            pack_metric_ids: version.packMetrics
                .filter((metric) => metric.available)
                .map((metric) => metric.id),
        });
        editor.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        editor.post(store.url(organisation.slug), { preserveScroll: true });
    };

    return (
        <div className="space-y-6 p-4">
            <Head title="Reporting publication" />
            <Heading
                title="Reporting publication"
                description="Approve metrics for public impact pages and controlled reporting packs."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Create a reporting publication draft</CardTitle>
                </CardHeader>
                <CardContent>
                    <form className="space-y-6" onSubmit={submit}>
                        <label className="relative block">
                            <span className="sr-only">Search metrics</span>
                            <Search className="text-muted-foreground absolute top-2.5 left-3 size-4" />
                            <Input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search by name, domain, or description"
                                className="pl-9"
                            />
                        </label>

                        {filteredMetrics.length === 0 ? (
                            <p className="text-muted-foreground rounded-lg border p-4 text-sm">
                                No registered metrics match that search.
                            </p>
                        ) : (
                            <fieldset
                                aria-describedby="destination-help destination-errors"
                                className="space-y-3"
                            >
                                <legend className="font-semibold">
                                    Choose where each metric is published
                                </legend>
                                <p
                                    id="destination-help"
                                    className="text-muted-foreground text-sm"
                                >
                                    The public impact page carries aggregated
                                    metrics appropriate for unrestricted
                                    publication. Reporting packs carry metrics
                                    for board, funder, or other approved
                                    exports. A metric may go to both, one, or
                                    neither.
                                </p>
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="w-full text-left text-sm">
                                        <caption className="sr-only">
                                            Registered metrics, with a checkbox
                                            per publication destination
                                        </caption>
                                        <thead className="bg-muted/50">
                                            <tr>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-2 font-medium"
                                                >
                                                    Metric
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="w-28 px-4 py-2 text-center font-medium"
                                                >
                                                    Public page
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="w-28 px-4 py-2 text-center font-medium"
                                                >
                                                    Reporting pack
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y">
                                            {filteredMetrics.map((metric) => (
                                                <tr
                                                    key={metric.id}
                                                    className="hover:bg-muted/40"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="px-4 py-3 text-left align-top font-normal"
                                                    >
                                                        <span className="flex flex-wrap items-center gap-2 font-medium">
                                                            {metric.label}
                                                            <Badge variant="outline">
                                                                {metric.unit}
                                                            </Badge>
                                                            <Badge variant="outline">
                                                                {metric.domain}
                                                            </Badge>
                                                        </span>
                                                        <span className="text-muted-foreground mt-1 block max-w-prose text-sm">
                                                            {metric.description}
                                                        </span>
                                                    </th>
                                                    <MetricToggleCell
                                                        checked={editor.data.public_metric_ids.includes(
                                                            metric.id,
                                                        )}
                                                        onChange={(checked) =>
                                                            toggle(
                                                                'public_metric_ids',
                                                                metric.id,
                                                                checked,
                                                            )
                                                        }
                                                        label={`Publish ${metric.label} on the ${PUBLIC_LABEL}`}
                                                    />
                                                    <MetricToggleCell
                                                        checked={editor.data.pack_metric_ids.includes(
                                                            metric.id,
                                                        )}
                                                        onChange={(checked) =>
                                                            toggle(
                                                                'pack_metric_ids',
                                                                metric.id,
                                                                checked,
                                                            )
                                                        }
                                                        label={`Include ${metric.label} in ${PACK_LABEL}`}
                                                    />
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </fieldset>
                        )}

                        <div id="destination-errors">
                            <InputError
                                message={editor.errors.public_metric_ids}
                            />
                            <InputError
                                message={editor.errors.pack_metric_ids}
                            />
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-4 border-t pt-5">
                            <p
                                aria-live="polite"
                                className="text-muted-foreground text-sm"
                            >
                                <span className="text-foreground font-medium">
                                    {editor.data.public_metric_ids.length}
                                </span>{' '}
                                on the public page ·{' '}
                                <span className="text-foreground font-medium">
                                    {editor.data.pack_metric_ids.length}
                                </span>{' '}
                                in reporting packs
                                {hiddenSelectedCount > 0
                                    ? ` · ${hiddenSelectedCount} selected metric${hiddenSelectedCount === 1 ? '' : 's'} hidden by the current search`
                                    : ''}
                            </p>
                            <Button disabled={editor.processing}>
                                {editor.processing
                                    ? 'Creating draft…'
                                    : 'Create reporting draft'}
                            </Button>
                        </div>
                        {/*
                         * Selecting nothing is allowed and is the way to stop
                         * publishing, so it has to be stated rather than
                         * refused. Withdrawing the last metric used to fail
                         * validation, which left no way back out of publishing.
                         */}
                        {editor.data.public_metric_ids.length === 0 &&
                        editor.data.pack_metric_ids.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                This version publishes nothing. Activating it
                                withdraws every metric from the public page and
                                from reporting packs.
                            </p>
                        ) : null}
                    </form>
                </CardContent>
            </Card>

            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Version history</h2>
                {versions.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No reporting publication versions yet.
                    </p>
                ) : null}
                {versions.map((version) => (
                    <Card key={version.id}>
                        <CardContent className="space-y-3 pt-6">
                            {/*
                             * The actions sat in a column of their own, so they
                             * floated at the far right of a narrow table. They
                             * now share a line with the version they act on.
                             *
                             * Every row was titled "Reporting publication · v1",
                             * restating the page title once per version. There
                             * is only one reporting publication, so the version
                             * is the only part that varies.
                             */}
                            <div className="flex flex-wrap items-center justify-between gap-3">
                                <div className="flex items-center gap-2">
                                    <strong>v{version.version}</strong>
                                    <Badge>{version.status}</Badge>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            useAsStartingPoint(version)
                                        }
                                    >
                                        New version
                                    </Button>
                                    {version.canActivate ? (
                                        <Form
                                            {...activate.form([
                                                organisation.slug,
                                                version.id,
                                            ])}
                                        >
                                            <Button>Activate</Button>
                                        </Form>
                                    ) : null}
                                </div>
                            </div>
                            <VersionMetricMatrix version={version} />
                            {version.hasUnavailableMetrics ? (
                                <p className="text-muted-foreground text-sm">
                                    Create a new version to replace retired
                                    metrics before activation.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                ))}
            </section>
        </div>
    );
}

ReportingPublicationIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Reporting publication',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
