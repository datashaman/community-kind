import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { show as showCase } from '@/routes/cases';
import { show as showIntake } from '@/routes/intakes';
import { exportMethod as exportServiceOperations } from '@/routes/dashboard/service-operations';
import { exportMethod as exportImpact } from '@/routes/dashboard/impact';
import { exportMethod as exportImpactChart } from '@/routes/dashboard/impact/chart';
import type { DashboardInvitation } from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    serviceOperations: ServiceOperations;
    impact?: ImpactDashboard;
};

type Metric = {
    definition: {
        id: string;
        version: string;
        category: string;
        label: string;
        description: string;
        formula: string;
        unit: string;
    };
    value: number | null;
    availability: 'available' | 'unavailable' | 'suppressed';
    sampleSize: number | null;
    comparison: { priorValue: number; change: number } | null;
};

type ImpactDashboard = {
    registryVersion: string;
    fictional: boolean;
    freshAt: string;
    timezone: string;
    currency: string;
    period: { start: string; endExclusive: string };
    filters: Record<string, string | number | null>;
    minimumCohort: number;
    metrics: Metric[];
    options: {
        programs: Array<{ id: number; name: string }>;
        areas: string[];
        locations: string[];
        cohorts: Array<{ value: string; label: string }>;
        campaigns: Array<{ id: number; name: string }>;
    };
};

type ServiceOperationsRow = {
    id: number | string;
    caseId?: string;
    program: string;
    status?: string;
    urgency?: string;
    dueAt?: string | null;
};

type ServiceOperations = {
    programs: Array<{ id: number; name: string }>;
    selectedProgramId: number | null;
    counts: Record<QueueKey, number>;
    caseload: ServiceOperationsRow[];
    waitlist: ServiceOperationsRow[];
    overdue: ServiceOperationsRow[];
    risks: ServiceOperationsRow[];
    referrals: ServiceOperationsRow[];
};

const queueDefinitions = [
    { key: 'caseload', label: 'Active caseload' },
    { key: 'waitlist', label: 'Waitlist' },
    { key: 'overdue', label: 'Overdue actions' },
    { key: 'risks', label: 'Unresolved risks' },
    { key: 'referrals', label: 'External referrals' },
] as const;

type QueueKey = (typeof queueDefinitions)[number]['key'];

export default function Dashboard({
    pendingInvitations = [],
    serviceOperations,
    impact,
}: Props) {
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );
    const organisation = usePage().props.currentOrganisation!;

    return (
        <>
            <Head title="Dashboard" />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4 sm:p-6">
                {/*
                 * Both visible headings below name a section rather than the
                 * page, and the impact section renders first, so the page
                 * title is carried non-visually to avoid restating the
                 * breadcrumb.
                 */}
                <h1 className="sr-only">Dashboard</h1>
                {impact ? <ImpactMetrics impact={impact} /> : null}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 className="font-display text-3xl font-semibold tracking-[-0.015em]">
                            Service operations
                        </h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Work requiring attention within your current scope.
                        </p>
                    </div>
                    <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                        <Form
                            action={dashboard.url(organisation.slug)}
                            method="get"
                            className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"
                        >
                            <label className="sr-only" htmlFor="program-filter">
                                Filter by program
                            </label>
                            <select
                                id="program-filter"
                                name="program_id"
                                defaultValue={
                                    serviceOperations.selectedProgramId ?? ''
                                }
                                className="bg-card h-9 w-full rounded-md border px-3 sm:w-auto"
                            >
                                <option value="">
                                    All authorised programs
                                </option>
                                {serviceOperations.programs.map((program) => (
                                    <option key={program.id} value={program.id}>
                                        {program.name}
                                    </option>
                                ))}
                            </select>
                            <Button variant="outline">Apply</Button>
                        </Form>
                        <Button asChild variant="outline">
                            <a
                                href={exportServiceOperations.url(
                                    organisation.slug,
                                    serviceOperations.selectedProgramId
                                        ? {
                                              query: {
                                                  program_id:
                                                      serviceOperations.selectedProgramId,
                                              },
                                          }
                                        : undefined,
                                )}
                            >
                                Export safe worklist
                            </a>
                        </Button>
                    </div>
                </div>

                <section
                    aria-label="Work queues"
                    className="grid gap-5 xl:grid-cols-2"
                >
                    {queueDefinitions.map(({ key, label }) => {
                        const rows = serviceOperations[key] ?? [];

                        return (
                            <Card key={key}>
                                <CardHeader className="flex-row items-baseline justify-between gap-3 space-y-0">
                                    <CardTitle>{label}</CardTitle>
                                    <p
                                        className="text-muted-foreground text-sm tabular-nums"
                                        aria-live="polite"
                                    >
                                        {serviceOperations.counts[key]} open
                                    </p>
                                </CardHeader>
                                <CardContent>
                                    {rows.length === 0 ? (
                                        <div className="bg-muted/40 rounded-lg border border-dashed p-4">
                                            <p className="font-medium">
                                                Queue clear
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                No accessible work in this
                                                queue.
                                            </p>
                                        </div>
                                    ) : (
                                        <ul
                                            className="divide-y"
                                            aria-label={label}
                                        >
                                            {rows.map((row) => (
                                                <li
                                                    key={row.id}
                                                    className="flex items-center justify-between gap-3 py-3"
                                                >
                                                    <div>
                                                        <p className="font-medium">
                                                            {row.program}
                                                        </p>
                                                        <p className="text-muted-foreground text-sm">
                                                            {row.status?.replaceAll(
                                                                '_',
                                                                ' ',
                                                            ) ??
                                                                (key === 'risks'
                                                                    ? 'Safeguarding review'
                                                                    : 'Action required')}
                                                            {row.dueAt
                                                                ? ` · due ${new Date(row.dueAt).toLocaleString()}`
                                                                : ''}
                                                        </p>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        {row.urgency ? (
                                                            <Badge variant="outline">
                                                                {row.urgency}
                                                            </Badge>
                                                        ) : null}
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={
                                                                    key ===
                                                                    'waitlist'
                                                                        ? showIntake.url(
                                                                              [
                                                                                  organisation.slug,
                                                                                  row.id,
                                                                              ],
                                                                          )
                                                                        : showCase.url(
                                                                              [
                                                                                  organisation.slug,
                                                                                  row.caseId ??
                                                                                      row.id,
                                                                              ],
                                                                          )
                                                                }
                                                            >
                                                                Open
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>
            </div>
        </>
    );
}

/*
 * The logic-model sequence, in order. Labels are spelled out rather than
 * derived from the key: the heading used to append "s", which rendered
 * "activitys", and the next category added would hit the same problem.
 */
const categories = [
    { key: 'input', label: 'Input' },
    { key: 'activity', label: 'Activity' },
    { key: 'output', label: 'Output' },
    { key: 'outcome', label: 'Outcome' },
] as const;

function ImpactMetrics({ impact }: { impact: ImpactDashboard }) {
    const organisation = usePage().props.currentOrganisation!;

    /*
     * Ordered by the logic model — inputs cause activities cause outputs cause
     * outcomes — so the row reads left to right in the order the theory of
     * change runs, rather than in whatever order the registry returned.
     */
    const orderedMetrics = categories.flatMap(({ key, label }) =>
        impact.metrics
            .filter((metric) => metric.definition.category === key)
            .map((metric) => ({ metric, categoryLabel: label })),
    );

    if (impact.metrics.length === 0) return null;

    return (
        <section className="space-y-5" aria-labelledby="impact-heading">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <h2
                            id="impact-heading"
                            className="font-display text-2xl font-semibold"
                        >
                            Reconciled impact
                        </h2>
                        <Badge variant="outline">Fictional data</Badge>
                    </div>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Registry {impact.registryVersion} · refreshed{' '}
                        {new Date(impact.freshAt).toLocaleString()} ·{' '}
                        {impact.timezone} · {impact.currency} · period end is
                        exclusive
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <a
                            href={exportImpact.url(organisation.slug, {
                                query: impact.filters,
                            })}
                        >
                            Export accessible CSV
                        </a>
                    </Button>
                    <Button asChild variant="outline">
                        <a
                            href={exportImpactChart.url(organisation.slug, {
                                query: impact.filters,
                            })}
                        >
                            Export accessible SVG
                        </a>
                    </Button>
                </div>
            </div>
            <Form
                action={dashboard.url(organisation.slug)}
                method="get"
                className="grid gap-3 rounded-lg border p-4 md:grid-cols-4"
            >
                <Filter
                    label="Period start"
                    name="period_start"
                    type="date"
                    value={impact.period.start.slice(0, 10)}
                />
                <Filter
                    label="Exclusive period end"
                    name="period_end"
                    type="date"
                    value={impact.period.endExclusive.slice(0, 10)}
                />
                <SelectFilter
                    label="Program"
                    name="program_id"
                    value={impact.filters.program_id}
                    options={impact.options.programs.map((item) => ({
                        value: item.id,
                        label: item.name,
                    }))}
                />
                <SelectFilter
                    label="Service area"
                    name="area"
                    value={impact.filters.area}
                    options={impact.options.areas.map((item) => ({
                        value: item,
                        label: item,
                    }))}
                />
                <SelectFilter
                    label="Location"
                    name="location"
                    value={impact.filters.location}
                    options={impact.options.locations.map((item) => ({
                        value: item,
                        label: item,
                    }))}
                />
                <SelectFilter
                    label="Cohort"
                    name="cohort"
                    value={impact.filters.cohort}
                    options={impact.options.cohorts}
                />
                <SelectFilter
                    label="Campaign"
                    name="campaign_id"
                    value={impact.filters.campaign_id}
                    options={impact.options.campaigns.map((item) => ({
                        value: item.id,
                        label: item.name,
                    }))}
                />
                <Button className="self-end">Apply reporting filters</Button>
            </Form>
            {/*
             * A card carries a label and a figure. Its category is an overline
             * rather than a heading above the column, because the reader is
             * looking at one metric, not opening a section. Everything else the
             * card used to carry — description, registry code, formula — is in
             * the disclosure below, where a reader who wants provenance can
             * find it without four cards restating it.
             */}
            <ol className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {orderedMetrics.map(({ metric, categoryLabel }) => (
                    <li key={metric.definition.id}>
                        {/*
                         * `display: contents` on the item would drop list
                         * semantics in several browsers, so the item stays a
                         * real grid cell and the card fills it.
                         */}
                        <Card className="h-full justify-between gap-3">
                            <CardHeader className="gap-1">
                                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                    {categoryLabel}
                                </p>
                                <CardTitle className="text-sm">
                                    {metric.definition.label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="font-display text-3xl font-semibold tabular-nums">
                                    {metricValue(metric, impact.currency)}
                                </p>
                                {metric.comparison ? (
                                    <p className="text-muted-foreground mt-1 text-xs tabular-nums">
                                        {metric.comparison.change > 0
                                            ? '+'
                                            : ''}
                                        {metric.comparison.change} on the prior
                                        period
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    </li>
                ))}
            </ol>
            <details className="text-sm">
                <summary className="cursor-pointer font-medium">
                    How these figures are calculated
                </summary>
                <dl className="mt-3 grid gap-3 sm:grid-cols-2">
                    {orderedMetrics.map(({ metric, categoryLabel }) => (
                        <div key={metric.definition.id}>
                            <dt className="font-medium">
                                {metric.definition.label}
                                <span className="text-muted-foreground font-normal">
                                    {' '}
                                    · {categoryLabel}
                                </span>
                            </dt>
                            <dd className="text-muted-foreground">
                                {metric.definition.description} Counted as{' '}
                                {metric.definition.formula}, from registry
                                definition {metric.definition.id}@
                                {metric.definition.version}.
                            </dd>
                        </div>
                    ))}
                </dl>
            </details>
            <MetricChart metrics={impact.metrics} currency={impact.currency} />
            {/*
             * Suppression only applies once a service area, location or cohort
             * filter is set — see BuildImpactDashboard's `sensitiveSlice`. The
             * old wording named neither the trigger nor the reason, so a reader
             * could not tell whether it applied to what they were looking at.
             */}
            <p className="text-muted-foreground text-xs">
                Filtering by service area, location, or cohort can narrow a
                figure to fewer than {impact.minimumCohort} people. Those
                figures are withheld rather than shown, because a count that
                small can identify the people in it. No figure here opens onto
                the people or cases behind it.
            </p>
        </section>
    );
}

function MetricChart({
    metrics,
    currency,
}: {
    metrics: Metric[];
    currency: string;
}) {
    const available = metrics.filter(
        (metric) =>
            metric.availability === 'available' && metric.value !== null,
    );

    /*
     * Grouped by unit, in the order the units first appear. A bar is only
     * meaningful against other bars on the same scale, and the groups now say
     * that structurally instead of the description warning about it in prose.
     */
    const unitGroups = available.reduce<
        Array<{ unit: string; metrics: Metric[] }>
    >((groups, metric) => {
        const group = groups.find(
            (candidate) => candidate.unit === metric.definition.unit,
        );
        if (group) group.metrics.push(metric);
        else groups.push({ unit: metric.definition.unit, metrics: [metric] });

        return groups;
    }, []);

    return (
        <figure
            className="space-y-3 rounded-lg border p-4"
            aria-labelledby="impact-chart-title"
            aria-describedby="impact-chart-description"
        >
            <figcaption>
                <h3 id="impact-chart-title" className="font-semibold">
                    Presentation chart
                </h3>
                <p
                    id="impact-chart-description"
                    className="text-muted-foreground text-sm"
                >
                    A bar shows a value against the largest on its own scale.
                    Values that are suppressed, unavailable, or alone on their
                    scale have none.
                </p>
            </figcaption>
            <div className="space-y-5" aria-hidden="true">
                {unitGroups.map(({ unit, metrics: unitMetrics }) => {
                    const maximum = Math.max(
                        ...unitMetrics.map((metric) =>
                            Math.abs(metric.value ?? 0),
                        ),
                    );
                    /*
                     * One metric on a scale would draw a full-width bar
                     * whatever its value, which reads as "the most" and means
                     * nothing. Net raised is the only currency metric, so it
                     * did exactly that.
                     */
                    const comparable = unitMetrics.length > 1 && maximum > 0;
                    const largest = unitMetrics.find(
                        (metric) => Math.abs(metric.value ?? 0) === maximum,
                    );

                    return (
                        <div key={unit} className="space-y-3">
                            <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                                {unitLabel(unit, currency)}
                                {comparable && largest
                                    ? ` · scaled to ${metricValue(largest, currency)}`
                                    : null}
                            </p>
                            {unitMetrics.map((metric) => (
                                <div
                                    key={metric.definition.id}
                                    className="grid gap-1"
                                >
                                    <div className="flex justify-between gap-3 text-sm">
                                        <span>{metric.definition.label}</span>
                                        <strong>
                                            {metricValue(metric, currency)}
                                        </strong>
                                    </div>
                                    {comparable ? (
                                        <div className="bg-muted h-4 rounded-sm">
                                            {/*
                                             * A zero gets no sliver. The old
                                             * `min-w-1` floor made a true zero
                                             * and a true near-zero identical.
                                             */}
                                            {Math.abs(metric.value ?? 0) > 0 ? (
                                                <div
                                                    className="bg-primary h-4 min-w-1 rounded-sm"
                                                    style={{
                                                        width: `${(Math.abs(metric.value ?? 0) / maximum) * 100}%`,
                                                    }}
                                                />
                                            ) : null}
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                    );
                })}
            </div>
            {/*
             * `sr-only` clips an absolutely positioned box, but a table's
             * caption is laid out outside that box and escapes the clip, so
             * the caption was showing through beneath the bars. Hiding the
             * wrapper instead takes the caption with it.
             */}
            <div className="sr-only">
                <table>
                    <caption>Nonvisual equivalent of the impact chart</caption>
                    <thead>
                        <tr>
                            <th scope="col">Metric</th>
                            <th scope="col">Value</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {metrics.map((metric) => (
                            <tr key={metric.definition.id}>
                                <th scope="row">{metric.definition.label}</th>
                                <td>{metricValue(metric, currency)}</td>
                                <td>{metric.availability}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </figure>
    );
}

const unitLabels: Record<string, string> = {
    count: 'Counts',
    percent: 'Percentages',
};

/* Currency names the Organisation's own currency rather than the word. */
const unitLabel = (unit: string, currency: string) =>
    unit === 'currency' ? currency : (unitLabels[unit] ?? unit);

function metricValue(metric: Metric, currency: string) {
    if (metric.availability === 'suppressed') return 'Suppressed';
    if (metric.availability === 'unavailable' || metric.value === null)
        return 'Unavailable';
    if (metric.definition.unit === 'percent')
        return `${metric.value.toFixed(1)}%`;
    if (metric.definition.unit === 'currency')
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(metric.value);
    return metric.value.toLocaleString();
}

function Filter({
    label,
    name,
    type,
    value,
}: {
    label: string;
    name: string;
    type: string;
    value: string;
}) {
    return (
        <label className="grid gap-1 text-sm">
            <span>{label}</span>
            <input
                className="h-9 rounded-md border bg-transparent px-3"
                name={name}
                type={type}
                defaultValue={value}
            />
        </label>
    );
}

function SelectFilter({
    label,
    name,
    value,
    options,
}: {
    label: string;
    name: string;
    value: string | number | null | undefined;
    options: Array<{ value: string | number; label: string }>;
}) {
    return (
        <label className="grid gap-1 text-sm">
            <span>{label}</span>
            <select
                className="h-9 rounded-md border bg-transparent px-3"
                name={name}
                defaultValue={value ?? ''}
            >
                <option value="">All</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

Dashboard.layout = (props: {
    currentOrganisation?: { slug: string } | null;
}) => ({
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: props.currentOrganisation
                ? dashboard(props.currentOrganisation.slug)
                : '/',
        },
    ],
});
