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
