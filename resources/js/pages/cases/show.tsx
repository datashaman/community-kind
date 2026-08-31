import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show as showIntake } from '@/routes/intakes';
import { store as storeItem } from '@/routes/cases/items';
import { store as transitionItem } from '@/routes/cases/items/transitions';
import { store as transitionCase } from '@/routes/cases/transitions';

const nextStates: Record<string, Record<string, string[]>> = {
    goal: {
        draft: ['active', 'cancelled'],
        active: ['achieved', 'not_achieved', 'cancelled', 'withdrawn'],
    },
    service: {
        planned: ['scheduled', 'completed', 'cancelled'],
        scheduled: ['completed', 'cancelled', 'not_delivered'],
    },
    referral: {
        draft: ['sent', 'cancelled'],
        sent: [
            'acknowledged',
            'connected',
            'not_connected',
            'cancelled',
            'carry_forward',
        ],
        acknowledged: [
            'connected',
            'not_connected',
            'cancelled',
            'carry_forward',
        ],
    },
    task: { open: ['completed', 'cancelled'] },
    appointment: { scheduled: ['completed', 'cancelled', 'no_show'] },
    note: { draft: ['finalized'] },
};

const reasonCodes = [
    'achieved',
    'not_achieved',
    'withdrawn',
    'no_longer_needed',
    'client_cancelled',
    'provider_cancelled',
    'not_available',
    'unable_to_connect',
    'no_show',
    'completed_elsewhere',
    'follow_up_in_new_case',
    'stable_tenancy',
];

function whenNow() {
    return new Date().toISOString().slice(0, 16);
}

function ItemCard({ type, item, args, services, canUpdate }: any) {
    const options = nextStates[type]?.[item.status] ?? [];

    return (
        <div className="space-y-3 rounded-lg border p-4">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <strong>
                        {item.content.title ??
                            item.content.summary ??
                            item.content.destination ??
                            item.content}
                    </strong>
                    {(item.content.description ?? item.content.purpose) ? (
                        <p className="text-muted-foreground mt-1 text-sm">
                            {item.content.description ?? item.content.purpose}
                        </p>
                    ) : null}
                </div>
                <Badge variant="outline">
                    {item.status.replaceAll('_', ' ')}
                </Badge>
            </div>
            {item.dueAt ? (
                <p
                    className={
                        item.overdue
                            ? 'text-destructive text-sm'
                            : 'text-muted-foreground text-sm'
                    }
                >
                    Due {new Date(item.dueAt).toLocaleString()}
                    {item.overdue ? ' · overdue' : ''}
                </p>
            ) : null}
            {item.reason ? (
                <p className="text-muted-foreground text-sm">
                    Reason: {item.reason}
                </p>
            ) : null}
            {canUpdate && options.length > 0 ? (
                <Form
                    action={transitionItem.url([
                        args[0],
                        args[1],
                        type,
                        item.id,
                    ])}
                    method="post"
                    className="grid gap-2 sm:grid-cols-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <input
                                type="hidden"
                                name="expected_version"
                                value={item.version}
                            />
                            <Input
                                type="datetime-local"
                                name="effective_at"
                                defaultValue={whenNow()}
                                required
                            />
                            <select
                                name="status"
                                className="h-9 rounded-md border bg-transparent px-3"
                                required
                            >
                                {options.map((status: string) => (
                                    <option key={status} value={status}>
                                        {status.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="reason"
                                className="h-9 rounded-md border bg-transparent px-3"
                            >
                                <option value="">No reason</option>
                                {reasonCodes.map((reason) => (
                                    <option key={reason} value={reason}>
                                        {reason.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            {type === 'appointment' ? (
                                <select
                                    name="completed_service_id"
                                    className="h-9 rounded-md border bg-transparent px-3"
                                >
                                    <option value="">No linked service</option>
                                    {services
                                        .filter(
                                            (service: any) =>
                                                service.status === 'completed',
                                        )
                                        .map((service: any) => (
                                            <option
                                                key={service.id}
                                                value={service.id}
                                            >
                                                {service.serviceCode}
                                            </option>
                                        ))}
                                </select>
                            ) : null}
                            <Button disabled={processing}>
                                Apply transition
                            </Button>
                            <InputError
                                className="sm:col-span-4"
                                message={errors.status}
                            />
                        </>
                    )}
                </Form>
            ) : null}
        </div>
    );
}

export default function CaseShow({ caseRecord, canUpdate }: any) {
    const organisation = usePage().props.currentOrganisation!;
    const args = [organisation.slug, caseRecord.id] as [string, string];
    const [kind, setKind] = useState('goal');
    const caseTransitions: Record<string, string[]> = {
        open: ['active', 'on_hold', 'closed', 'cancelled'],
        active: ['on_hold', 'closed'],
        on_hold: ['active', 'closed'],
    };
    const availableCaseTransitions = caseTransitions[caseRecord.status] ?? [];
    const [caseStatus, setCaseStatus] = useState(
        availableCaseTransitions[0] ?? '',
    );

    return (
        <>
            <Head title={`${caseRecord.party.displayName} case`} />
            <div className="space-y-8 p-4">
                <div>
                    <Link
                        className="text-muted-foreground text-sm"
                        href={showIntake.url([
                            organisation.slug,
                            caseRecord.intakeId,
                        ])}
                    >
                        ← Service request
                    </Link>
                    <Heading
                        title={caseRecord.party.displayName}
                        description={caseRecord.program.name}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Badge>{caseRecord.status.replaceAll('_', ' ')}</Badge>
                        <Badge variant="outline">
                            {caseRecord.confidentiality}
                        </Badge>
                        {caseRecord.assignments
                            .filter((item: any) => item.status === 'active')
                            .map((item: any) => (
                                <Badge key={item.id} variant="secondary">
                                    {item.worker}
                                </Badge>
                            ))}
                    </div>
                </div>

                {caseRecord.safeContactBanner ? (
                    <div
                        role="alert"
                        className="rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-amber-950 dark:bg-amber-950/30 dark:text-amber-100"
                    >
                        <p className="text-xs font-semibold tracking-wide uppercase">
                            Contact safety
                        </p>
                        <p className="mt-1 font-medium">
                            {caseRecord.safeContactBanner}
                        </p>
                    </div>
                ) : null}

                {caseRecord.riskAssessments.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Highly restricted risk detail</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {caseRecord.riskAssessments.map((risk: any) => (
                                <div
                                    key={risk.id}
                                    className="rounded-lg border p-3 text-sm"
                                >
                                    <Badge variant="destructive">
                                        {risk.classification.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </Badge>
                                    <p className="mt-2">{risk.content}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}

                {canUpdate ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Record case activity</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={storeItem.url(args)}
                                method="post"
                                className="grid gap-4 md:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor="kind">
                                                Record type
                                            </Label>
                                            <select
                                                id="kind"
                                                name="kind"
                                                value={kind}
                                                onChange={(event) =>
                                                    setKind(event.target.value)
                                                }
                                                className="h-9 w-full rounded-md border bg-transparent px-3"
                                            >
                                                {[
                                                    'goal',
                                                    'service',
                                                    'referral',
                                                    'task',
                                                    'appointment',
                                                    'interaction',
                                                    'note',
                                                ].map((value) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {value}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        {['goal', 'task'].includes(kind) ? (
                                            <>
                                                <div className="space-y-2">
                                                    <Label>Title</Label>
                                                    <Input
                                                        name="title"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>
                                                        {kind === 'goal'
                                                            ? 'Target date'
                                                            : 'Due date'}
                                                    </Label>
                                                    <Input
                                                        type="datetime-local"
                                                        name={
                                                            kind === 'goal'
                                                                ? 'target_at'
                                                                : 'due_at'
                                                        }
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>Details</Label>
                                                    <Textarea
                                                        name="description"
                                                        required
                                                    />
                                                </div>
                                            </>
                                        ) : null}
                                        {kind === 'service' ? (
                                            <>
                                                <div className="space-y-2">
                                                    <Label>Service code</Label>
                                                    <Input
                                                        name="service_code"
                                                        placeholder="housing_advice"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Scheduled for</Label>
                                                    <Input
                                                        type="datetime-local"
                                                        name="scheduled_for"
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>Summary</Label>
                                                    <Textarea
                                                        name="summary"
                                                        required
                                                    />
                                                </div>
                                            </>
                                        ) : null}
                                        {kind === 'referral' ? (
                                            <>
                                                <div className="space-y-2">
                                                    <Label>Destination</Label>
                                                    <Input
                                                        name="destination"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>
                                                        Sharing authority
                                                    </Label>
                                                    <Input
                                                        name="sharing_authority"
                                                        value="service_consent"
                                                        readOnly
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Purpose</Label>
                                                    <Textarea
                                                        name="purpose"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>
                                                        Minimum necessary
                                                        information
                                                    </Label>
                                                    <Textarea
                                                        name="minimum_necessary"
                                                        required
                                                    />
                                                </div>
                                            </>
                                        ) : null}
                                        {kind === 'appointment' ? (
                                            <>
                                                <div className="space-y-2">
                                                    <Label>Scheduled at</Label>
                                                    <Input
                                                        type="datetime-local"
                                                        name="scheduled_at"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Location</Label>
                                                    <Input
                                                        name="location"
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>Summary</Label>
                                                    <Textarea
                                                        name="summary"
                                                        required
                                                    />
                                                </div>
                                            </>
                                        ) : null}
                                        {kind === 'interaction' ? (
                                            <>
                                                <div className="space-y-2">
                                                    <Label>Occurred at</Label>
                                                    <Input
                                                        type="datetime-local"
                                                        name="occurred_at"
                                                        defaultValue={whenNow()}
                                                        required
                                                    />
                                                </div>
                                                <div className="space-y-2">
                                                    <Label>Type</Label>
                                                    <select
                                                        name="interaction_type"
                                                        className="h-9 w-full rounded-md border bg-transparent px-3"
                                                    >
                                                        <option value="in_person">
                                                            In person
                                                        </option>
                                                        <option value="telephone">
                                                            Telephone
                                                        </option>
                                                        <option value="email">
                                                            Email
                                                        </option>
                                                        <option value="video">
                                                            Video
                                                        </option>
                                                        <option value="other">
                                                            Other
                                                        </option>
                                                    </select>
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>Summary</Label>
                                                    <Textarea
                                                        name="summary"
                                                        required
                                                    />
                                                </div>
                                            </>
                                        ) : null}
                                        {kind === 'note' ? (
                                            <div className="space-y-2 md:col-span-2">
                                                <Label>Confidential note</Label>
                                                <Textarea
                                                    name="content"
                                                    required
                                                />
                                                <select
                                                    name="corrects_note_id"
                                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                                >
                                                    <option value="">
                                                        New note
                                                    </option>
                                                    {caseRecord.notes
                                                        .filter(
                                                            (note: any) =>
                                                                note.status ===
                                                                'finalized',
                                                        )
                                                        .map((note: any) => (
                                                            <option
                                                                key={note.id}
                                                                value={note.id}
                                                            >
                                                                Correction/addendum
                                                                to{' '}
                                                                {note.id.slice(
                                                                    0,
                                                                    8,
                                                                )}
                                                            </option>
                                                        ))}
                                                </select>
                                            </div>
                                        ) : null}
                                        <InputError
                                            className="md:col-span-2"
                                            message={errors.item}
                                        />
                                        <Button
                                            className="md:col-span-2"
                                            disabled={processing}
                                        >
                                            Add {kind}
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}

                <div className="grid gap-6 xl:grid-cols-2">
                    {[
                        ['Goals', 'goal', caseRecord.goals],
                        ['Services', 'service', caseRecord.services],
                        [
                            'External referrals',
                            'referral',
                            caseRecord.referrals,
                        ],
                        ['Tasks', 'task', caseRecord.tasks],
                        [
                            'Appointments',
                            'appointment',
                            caseRecord.appointments,
                        ],
                        [
                            'Case notes',
                            'note',
                            caseRecord.notes.map((note: any) => ({
                                ...note,
                                content: { title: note.content },
                            })),
                        ],
                    ].map(([title, type, items]: any) => (
                        <Card key={type}>
                            <CardHeader>
                                <CardTitle>{title}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {items.map((item: any) => (
                                    <ItemCard
                                        key={item.id}
                                        type={type}
                                        item={item}
                                        args={args}
                                        services={caseRecord.services}
                                        canUpdate={canUpdate}
                                    />
                                ))}
                                {items.length === 0 ? (
                                    <p className="text-muted-foreground text-sm">
                                        Nothing recorded.
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Interactions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {caseRecord.interactions.map((item: any) => (
                            <div
                                key={item.id}
                                className="rounded-lg border p-3 text-sm"
                            >
                                <strong>
                                    {item.type.replaceAll('_', ' ')}
                                </strong>{' '}
                                · {new Date(item.occurredAt).toLocaleString()}
                                <p className="text-muted-foreground mt-1">
                                    {item.content.summary}
                                </p>
                            </div>
                        ))}
                        {caseRecord.interactions.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No interactions recorded.
                            </p>
                        ) : null}
                    </CardContent>
                </Card>

                {canUpdate && availableCaseTransitions.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Case state and closure</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={transitionCase.url(args)}
                                method="post"
                                className="grid gap-4 md:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="expected_version"
                                            value={caseRecord.version}
                                        />
                                        <div className="space-y-2">
                                            <Label>Next status</Label>
                                            <select
                                                name="status"
                                                value={caseStatus}
                                                onChange={(event) =>
                                                    setCaseStatus(
                                                        event.target.value,
                                                    )
                                                }
                                                className="h-9 w-full rounded-md border bg-transparent px-3"
                                            >
                                                {availableCaseTransitions.map(
                                                    (status) => (
                                                        <option
                                                            key={status}
                                                            value={status}
                                                        >
                                                            {status.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Effective at</Label>
                                            <Input
                                                type="datetime-local"
                                                name="effective_at"
                                                defaultValue={whenNow()}
                                                required
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label>Reason code</Label>
                                            <select
                                                name="reason"
                                                className="h-9 w-full rounded-md border bg-transparent px-3"
                                            >
                                                <option value="">
                                                    No reason
                                                </option>
                                                <option value="goals_completed">
                                                    Goals completed
                                                </option>
                                                <option value="support_completed">
                                                    Support completed
                                                </option>
                                                <option value="client_withdrew">
                                                    Client withdrew
                                                </option>
                                                <option value="created_in_error">
                                                    Created in error
                                                </option>
                                                <option value="transferred">
                                                    Transferred
                                                </option>
                                            </select>
                                        </div>
                                        {caseStatus === 'closed' ? (
                                            <>
                                                <div className="space-y-2">
                                                    <Label>
                                                        Follow-up date
                                                    </Label>
                                                    <Input
                                                        type="datetime-local"
                                                        name="follow_up_at"
                                                    />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label>
                                                        Outcome narrative
                                                    </Label>
                                                    <Textarea
                                                        name="narrative"
                                                        required
                                                    />
                                                </div>
                                                {caseRecord.program.configuration.outcome_measures.map(
                                                    (measure: any) => (
                                                        <div
                                                            key={measure.key}
                                                            className="space-y-2"
                                                        >
                                                            <Label>
                                                                {measure.label}{' '}
                                                                ({measure.unit})
                                                            </Label>
                                                            <Input
                                                                type="number"
                                                                step="any"
                                                                name={`measures[${measure.key}]`}
                                                                required
                                                            />
                                                        </div>
                                                    ),
                                                )}
                                            </>
                                        ) : null}
                                        <InputError
                                            className="md:col-span-2"
                                            message={errors.status}
                                        />
                                        <Button
                                            className="md:col-span-2"
                                            disabled={processing}
                                        >
                                            Apply Case transition
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}

                {caseRecord.outcome ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Structured outcome</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p>{caseRecord.outcome.narrative}</p>
                            <div className="mt-3 flex flex-wrap gap-2">
                                {Object.entries(
                                    caseRecord.outcome.measures,
                                ).map(([key, value]) => (
                                    <Badge key={key} variant="outline">
                                        {key.replaceAll('_', ' ')}:{' '}
                                        {String(value)}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>Immutable workflow history</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {caseRecord.transitions.map((item: any) => (
                            <p key={item.id}>
                                {item.subjectType} v{item.version}:{' '}
                                {item.from ?? 'created'} → {item.to}
                                {item.reason ? ` — ${item.reason}` : ''} ·{' '}
                                {new Date(item.effectiveAt).toLocaleString()}
                            </p>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
