import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

function MetricSelection({
    title,
    description,
    metrics,
    selected,
    onToggle,
}: {
    title: string;
    description: string;
    metrics: Metric[];
    selected: string[];
    onToggle: (id: string, checked: boolean) => void;
}) {
    return (
        <fieldset className="space-y-3">
            <legend className="font-semibold">{title}</legend>
            <p className="text-muted-foreground text-sm">{description}</p>
            <div className="grid gap-2">
                {metrics.map((metric) => (
                    <label
                        key={metric.id}
                        className="hover:bg-muted/40 flex items-start gap-3 rounded-lg border p-3"
                    >
                        <input
                            type="checkbox"
                            className="mt-1"
                            checked={selected.includes(metric.id)}
                            onChange={(event) =>
                                onToggle(metric.id, event.target.checked)
                            }
                        />
                        <span className="min-w-0 flex-1">
                            <span className="flex flex-wrap items-center gap-2 font-medium">
                                {metric.label}
                                <Badge variant="outline">{metric.unit}</Badge>
                                <Badge variant="outline">{metric.domain}</Badge>
                            </span>
                            <span className="text-muted-foreground block text-sm">
                                {metric.description}
                            </span>
                            <span className="text-muted-foreground block truncate font-mono text-xs">
                                {metric.id}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

function VersionMetricList({
    title,
    metrics,
}: {
    title: string;
    metrics: SelectedMetric[];
}) {
    return (
        <div>
            <h3 className="text-sm font-semibold">{title}</h3>
            {metrics.length === 0 ? (
                <p className="text-muted-foreground text-sm">None selected</p>
            ) : (
                <ul className="mt-1 grid gap-1 text-sm">
                    {metrics.map((metric) => (
                        <li
                            key={metric.id}
                            className="flex flex-wrap items-center gap-2"
                        >
                            {metric.label}
                            {metric.available ? null : (
                                <Badge variant="destructive">
                                    Retired or unavailable
                                </Badge>
                            )}
                        </li>
                    ))}
                </ul>
            )}
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

    const selectedMetric = (id: string) =>
        metrics.find((metric) => metric.id === id);

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
                            <div className="grid gap-8 xl:grid-cols-2">
                                <MetricSelection
                                    title="Public impact page"
                                    description="Select at least one aggregated metric appropriate for unrestricted publication."
                                    metrics={filteredMetrics}
                                    selected={editor.data.public_metric_ids}
                                    onToggle={(id, checked) =>
                                        toggle('public_metric_ids', id, checked)
                                    }
                                />
                                <MetricSelection
                                    title="Approved reporting packs"
                                    description="Select at least one metric for board, funder, or other approved exports."
                                    metrics={filteredMetrics}
                                    selected={editor.data.pack_metric_ids}
                                    onToggle={(id, checked) =>
                                        toggle('pack_metric_ids', id, checked)
                                    }
                                />
                            </div>
                        )}
                        <InputError message={editor.errors.public_metric_ids} />
                        <InputError message={editor.errors.pack_metric_ids} />

                        <div className="bg-muted/40 grid gap-4 rounded-xl border p-5 lg:grid-cols-2">
                            <div>
                                <strong>Public preview</strong>
                                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                    {editor.data.public_metric_ids.map((id) => {
                                        const metric = selectedMetric(id);
                                        return (
                                            <div
                                                key={id}
                                                className="bg-background rounded-lg border p-3"
                                            >
                                                <div className="text-muted-foreground text-xs uppercase">
                                                    {metric?.domain}
                                                </div>
                                                <div className="font-semibold">
                                                    {metric?.label}
                                                </div>
                                                <div className="text-muted-foreground text-sm">
                                                    — {metric?.unit}
                                                </div>
                                            </div>
                                        );
                                    })}
                                    {editor.data.public_metric_ids.length ===
                                    0 ? (
                                        <p className="text-muted-foreground text-sm">
                                            Select at least one public metric.
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                            <div>
                                <strong>Reporting pack preview</strong>
                                <ol className="mt-2 grid gap-1 text-sm">
                                    {editor.data.pack_metric_ids.map(
                                        (id, metricIndex) => (
                                            <li key={id}>
                                                {metricIndex + 1}.{' '}
                                                {selectedMetric(id)?.label}
                                            </li>
                                        ),
                                    )}
                                </ol>
                            </div>
                        </div>

                        <Button disabled={editor.processing}>
                            {editor.processing
                                ? 'Creating draft…'
                                : 'Create reporting draft'}
                        </Button>
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
                        <CardContent className="grid gap-4 pt-6 lg:grid-cols-[1fr_auto]">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <strong>
                                        Reporting publication · v
                                        {version.version}
                                    </strong>
                                    <Badge>{version.status}</Badge>
                                </div>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <VersionMetricList
                                        title="Public impact page"
                                        metrics={version.publicMetrics}
                                    />
                                    <VersionMetricList
                                        title="Approved reporting packs"
                                        metrics={version.packMetrics}
                                    />
                                </div>
                                {version.hasUnavailableMetrics ? (
                                    <p className="text-muted-foreground text-sm">
                                        Create a new version to replace retired
                                        metrics before activation.
                                    </p>
                                ) : null}
                            </div>
                            <div className="flex flex-wrap items-start gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => useAsStartingPoint(version)}
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
