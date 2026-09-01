import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { approve, dispatch, index, show } from '@/routes/supporter-journeys';
import { store as transition } from '@/routes/supporter-journeys/recipients/transitions';
import { store as transitionJourney } from '@/routes/supporter-journeys/transitions';

type Recipient = {
    id: string;
    displayName: string;
    status: string;
    attemptCount: number;
    variant: string | null;
    events: Array<{ id: string; type: string }>;
    actionKeys: Record<string, string>;
};
type Journey = {
    id: string;
    name: string;
    subject: string;
    body: string;
    status: string;
    kind: string;
    channel: string;
    scheduledFor: string | null;
    pausedAt: string | null;
    experiment: { subject: string; body: string } | null;
    audienceName: string;
    audienceSnapshot: Array<{
        uuid: string;
        displayName: string;
        donationCount: number;
        activityFrequency: number;
        activityValue: number | null;
    }>;
    approvalHash: string | null;
    recipients: Recipient[];
};

export default function SupporterJourneyShow({
    journey,
    simulationOnly,
}: {
    journey: Journey;
    simulationOnly: boolean;
}) {
    const organisation = usePage().props.currentOrganisation!;
    const routeArgs: [string, string] = [organisation.slug, journey.id];

    return (
        <>
            <Head title={journey.name} />
            <div className="space-y-6 p-4">
                <Heading
                    title={journey.name}
                    description={`Audience: ${journey.audienceName}`}
                />
                <div className="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100">
                    <strong>Local simulation only.</strong> No email, SMS, or
                    external provider is contacted.
                    {!simulationOnly ? ' Simulation is disabled.' : ''}
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Preview and approval</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Badge>{journey.status}</Badge>
                        <p className="text-muted-foreground text-sm">
                            {journey.kind.replaceAll('_', ' ')} ·{' '}
                            {journey.channel}
                            {journey.scheduledFor
                                ? ` · scheduled ${new Date(journey.scheduledFor).toLocaleString()}`
                                : ''}
                        </p>
                        <div className="rounded-md border p-4">
                            <strong>
                                {render(
                                    journey.subject,
                                    journey.audienceSnapshot[0],
                                )}
                            </strong>
                            <p className="mt-3 whitespace-pre-wrap">
                                {render(
                                    journey.body,
                                    journey.audienceSnapshot[0],
                                )}
                            </p>
                        </div>
                        {journey.experiment ? (
                            <div className="rounded-md border border-dashed p-4">
                                <p className="text-sm font-medium">
                                    Variant B preview
                                </p>
                                <strong>
                                    {render(
                                        journey.experiment.subject,
                                        journey.audienceSnapshot[0],
                                    )}
                                </strong>
                                <p className="mt-3 whitespace-pre-wrap">
                                    {render(
                                        journey.experiment.body,
                                        journey.audienceSnapshot[0],
                                    )}
                                </p>
                            </div>
                        ) : null}
                        <p className="text-muted-foreground text-sm">
                            {journey.status === 'draft'
                                ? 'Approval freezes this content and the currently eligible audience.'
                                : `${journey.audienceSnapshot.length} supporters frozen · ${journey.approvalHash}`}
                        </p>
                        {journey.status === 'draft' ? (
                            <Form {...approve.form(routeArgs)}>
                                {({ processing }) => (
                                    <Button disabled={processing}>
                                        Approve and freeze
                                    </Button>
                                )}
                            </Form>
                        ) : journey.status !== 'paused' ? (
                            <Form {...dispatch.form(routeArgs)}>
                                {({ processing }) => (
                                    <Button disabled={processing}>
                                        Recheck eligibility and simulate
                                        dispatch
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                        {journey.status === 'approved' ? (
                            <div className="flex flex-wrap gap-2">
                                <Form
                                    {...transitionJourney.form(routeArgs)}
                                    className="flex gap-2"
                                >
                                    <input
                                        type="hidden"
                                        name="status"
                                        value="scheduled"
                                    />
                                    <input
                                        type="datetime-local"
                                        name="scheduled_for"
                                        required
                                        className="h-9 rounded border bg-transparent px-2"
                                    />
                                    <Button variant="outline">Schedule</Button>
                                </Form>
                                <JourneyTransition
                                    routeArgs={routeArgs}
                                    status="paused"
                                    label="Pause"
                                />
                            </div>
                        ) : null}
                        {journey.status === 'scheduled' ? (
                            <JourneyTransition
                                routeArgs={routeArgs}
                                status="paused"
                                label="Pause schedule"
                            />
                        ) : null}
                        {journey.status === 'paused' ? (
                            <JourneyTransition
                                routeArgs={routeArgs}
                                status="approved"
                                label="Resume approved"
                            />
                        ) : null}
                    </CardContent>
                </Card>
                <section
                    className="space-y-3"
                    aria-labelledby="recipient-results"
                >
                    <h2
                        id="recipient-results"
                        className="text-xl font-semibold"
                    >
                        Recipient outcomes
                    </h2>
                    {journey.recipients.map((recipient) => (
                        <Card key={recipient.id}>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3 pt-6">
                                <div>
                                    <strong>{recipient.displayName}</strong>
                                    <p className="text-muted-foreground text-sm">
                                        {recipient.status} ·{' '}
                                        {recipient.attemptCount} retries
                                        {recipient.variant
                                            ? ` · variant ${recipient.variant}`
                                            : ''}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {actionsFor(recipient.status).map(
                                        (type) => (
                                            <Form
                                                key={type}
                                                {...transition.form([
                                                    organisation.slug,
                                                    journey.id,
                                                    recipient.id,
                                                ])}
                                            >
                                                <input
                                                    type="hidden"
                                                    name="type"
                                                    value={type}
                                                />
                                                <input
                                                    type="hidden"
                                                    name="idempotency_key"
                                                    value={
                                                        recipient.actionKeys[
                                                            type
                                                        ]
                                                    }
                                                />
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    {type.replace('_', ' ')}
                                                </Button>
                                            </Form>
                                        ),
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </section>
            </div>
        </>
    );
}

function render(
    template: string,
    supporter?: {
        displayName: string;
        donationCount: number;
        activityFrequency: number;
        activityValue: number | null;
    },
) {
    return template
        .replaceAll(
            '{{ supporter_name }}',
            supporter?.displayName ?? 'Supporter',
        )
        .replaceAll(
            '{{ donation_count }}',
            String(supporter?.donationCount ?? 0),
        )
        .replaceAll(
            '{{ activity_frequency }}',
            String(supporter?.activityFrequency ?? 0),
        )
        .replaceAll(
            '{{ activity_value }}',
            String(supporter?.activityValue ?? 0),
        );
}

function JourneyTransition({
    routeArgs,
    status,
    label,
}: {
    routeArgs: [string, string];
    status: string;
    label: string;
}) {
    return (
        <Form {...transitionJourney.form(routeArgs)}>
            <input type="hidden" name="status" value={status} />
            <Button variant="outline">{label}</Button>
        </Form>
    );
}

function actionsFor(status: string): string[] {
    if (status === 'queued') return ['delivered', 'bounced', 'cancelled'];
    if (status === 'bounced') return ['retried'];
    if (status === 'delivered') return ['meaningful_action', 'unsubscribed'];
    return [];
}

SupporterJourneyShow.layout = (props: {
    currentOrganisation: { slug: string };
    journey: Journey;
}) => ({
    breadcrumbs: [
        {
            title: 'Welcome journeys',
            href: index(props.currentOrganisation.slug),
        },
        {
            title: props.journey.name,
            href: show([props.currentOrganisation.slug, props.journey.id]),
        },
    ],
});
