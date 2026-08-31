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
            <main className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                {impact ? <ImpactMetrics impact={impact} /> : null}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Service operations
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Work requiring attention within your current scope.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Form
                            action={dashboard.url(organisation.slug)}
                            method="get"
                            className="flex gap-2"
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
                                className="h-9 rounded-md border bg-transparent px-3"
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
                    aria-label="Work queue counts"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
                >
                    {queueDefinitions.map(({ key, label }) => (
                        <Card key={key}>
                            <CardHeader className="pb-2">
                                <CardTitle className="text-sm font-medium">
                                    {label}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p
                                    className="text-3xl font-semibold"
                                    aria-live="polite"
                                >
                                    {serviceOperations.counts[key]}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </section>

                <div className="grid gap-5 xl:grid-cols-2">
                    {queueDefinitions.map(({ key, label }) => {
                        const rows = serviceOperations[key] ?? [];

                        return (
                            <Card key={key}>
                                <CardHeader>
                                    <CardTitle>{label}</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {rows.length === 0 ? (
                                        <p className="text-muted-foreground text-sm">
                                            No accessible work in this queue.
                                        </p>
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
                </div>
            </main>
        </>
    );
}

const categories = ['input', 'activity', 'output', 'outcome'] as const;

function ImpactMetrics({ impact }: { impact: ImpactDashboard }) {
    const organisation = usePage().props.currentOrganisation!;

    if (impact.metrics.length === 0) return null;

    return (
        <section className="space-y-5" aria-labelledby="impact-heading">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <h1
                            id="impact-heading"
                            className="text-2xl font-semibold"
                        >
                            Reconciled impact
                        </h1>
                        <Badge variant="outline">Fictional data</Badge>
                    </div>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Registry {impact.registryVersion} · refreshed{' '}
                        {new Date(impact.freshAt).toLocaleString()} ·{' '}
                        {impact.timezone} · {impact.currency} · period end is
                        exclusive
                    </p>
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
            {categories.map((category) => {
                const metrics = impact.metrics.filter(
                    (metric) => metric.definition.category === category,
                );
                if (metrics.length === 0) return null;

                return (
                    <div key={category} className="space-y-2">
                        <h2 className="text-sm font-semibold tracking-wide uppercase">
                            {category}s
                        </h2>
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                            {metrics.map((metric) => (
                                <Card key={metric.definition.id}>
                                    <CardHeader className="pb-2">
                                        <CardTitle className="text-sm">
                                            {metric.definition.label}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2">
                                        <p className="text-3xl font-semibold">
                                            {metricValue(
                                                metric,
                                                impact.currency,
                                            )}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {metric.definition.description}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            Definition {metric.definition.id}@
                                            {metric.definition.version} ·{' '}
                                            {metric.definition.formula}
                                        </p>
                                        {metric.comparison ? (
                                            <p className="text-xs">
                                                Prior-period change:{' '}
                                                {metric.comparison.change > 0
                                                    ? '+'
                                                    : ''}
                                                {metric.comparison.change}
                                            </p>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                );
            })}
            <p className="text-muted-foreground text-xs">
                Slices with 1–{impact.minimumCohort - 1} people are suppressed.
                Aggregates do not provide person or case drill-down.
            </p>
        </section>
    );
}

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
