import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/intakes';
import { store as assign } from '@/routes/intakes/assignments';
import { store as transition } from '@/routes/intakes/transitions';
import {
    destroy as reverseDuplicate,
    store as reviewDuplicate,
} from '@/routes/duplicate-reviews';

const transitions: Record<string, string[]> = {
    draft: ['submitted', 'withdrawn'],
    submitted: ['under_review', 'withdrawn'],
    under_review: [
        'waitlisted',
        'accepted',
        'redirected',
        'declined',
        'withdrawn',
    ],
    waitlisted: ['under_review', 'accepted', 'redirected', 'withdrawn'],
};

export default function IntakeShow({ intake, canTransition, workers }: any) {
    const organisation = usePage().props.currentOrganisation!;
    const args = [organisation.slug, intake.id] as [string, string];
    const availableTransitions = transitions[intake.status] ?? [];

    return (
        <>
            <Head title={`${intake.party.displayName} service request`} />
            <div className="space-y-8 p-4">
                <div>
                    <Link
                        className="text-muted-foreground text-sm"
                        href={index.url(organisation.slug)}
                    >
                        ← Service requests
                    </Link>
                    <Heading
                        title={intake.party.displayName}
                        description={intake.program.name}
                    />
                    <div className="flex gap-2">
                        <Badge>{intake.status.replace('_', ' ')}</Badge>
                        <Badge variant="outline">{intake.urgency}</Badge>
                        <Badge variant="outline">
                            {intake.eligibilityStatus.replace('_', ' ')}
                        </Badge>
                    </div>
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Presenting needs</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <p className="whitespace-pre-wrap">
                                {intake.presentingNeeds}
                            </p>
                            <p className="text-muted-foreground whitespace-pre-wrap">
                                {intake.narrative}
                            </p>
                            {Object.entries(intake.intakeFields).map(
                                ([key, value]) => (
                                    <p key={key}>
                                        <strong>
                                            {key.replaceAll('_', ' ')}:
                                        </strong>{' '}
                                        {String(value)}
                                    </p>
                                ),
                            )}
                            <div className="flex flex-wrap gap-2">
                                {intake.riskFlags.map((risk: string) => (
                                    <Badge key={risk} variant="destructive">
                                        {risk.replaceAll('_', ' ')}
                                    </Badge>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Triage and decision</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {canTransition &&
                            availableTransitions.length > 0 ? (
                                <Form
                                    action={transition.url(args)}
                                    method="post"
                                    className="space-y-4"
                                    resetOnSuccess={false}
                                >
                                    {({ errors, processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="expected_version"
                                                value={intake.version}
                                            />
                                            <div>
                                                <Label htmlFor="status">
                                                    Next status
                                                </Label>
                                                <select
                                                    id="status"
                                                    name="status"
                                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                                >
                                                    {availableTransitions.map(
                                                        (status) => (
                                                            <option
                                                                key={status}
                                                                value={status}
                                                            >
                                                                {status.replace(
                                                                    '_',
                                                                    ' ',
                                                                )}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                <InputError
                                                    message={errors.status}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="urgency">
                                                    Urgency
                                                </Label>
                                                <select
                                                    id="urgency"
                                                    name="urgency"
                                                    defaultValue={
                                                        intake.urgency
                                                    }
                                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                                >
                                                    <option value="routine">
                                                        Routine
                                                    </option>
                                                    <option value="priority">
                                                        Priority
                                                    </option>
                                                    <option value="urgent">
                                                        Urgent
                                                    </option>
                                                </select>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    Urgent prioritises work; it
                                                    is not an emergency-dispatch
                                                    service.
                                                </p>
                                            </div>
                                            <div>
                                                <Label htmlFor="eligibility_status">
                                                    Eligibility
                                                </Label>
                                                <select
                                                    id="eligibility_status"
                                                    name="eligibility_status"
                                                    defaultValue={
                                                        intake.eligibilityStatus
                                                    }
                                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                                >
                                                    <option value="needs_review">
                                                        Needs review
                                                    </option>
                                                    <option value="eligible">
                                                        Eligible
                                                    </option>
                                                    <option value="ineligible">
                                                        Ineligible
                                                    </option>
                                                </select>
                                            </div>
                                            {(
                                                intake.configuration
                                                    .eligibility_fields ?? []
                                            ).map((field: any) => (
                                                <label
                                                    key={field.key}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <input
                                                        type="hidden"
                                                        name={`eligibility_context[${field.key}]`}
                                                        value="0"
                                                    />
                                                    <input
                                                        type="checkbox"
                                                        name={`eligibility_context[${field.key}]`}
                                                        value="1"
                                                        defaultChecked={Boolean(
                                                            intake
                                                                .eligibilityContext[
                                                                field.key
                                                            ],
                                                        )}
                                                    />
                                                    {field.label}
                                                </label>
                                            ))}
                                            {(
                                                intake.configuration
                                                    .risk_flags ?? []
                                            ).map((risk: any) => (
                                                <label
                                                    key={risk.key}
                                                    className="flex items-center gap-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="risk_flags[]"
                                                        value={risk.key}
                                                        defaultChecked={intake.riskFlags.includes(
                                                            risk.key,
                                                        )}
                                                    />
                                                    {risk.label}
                                                </label>
                                            ))}
                                            <input
                                                type="hidden"
                                                name="risk_flags[]"
                                                value=""
                                            />
                                            <div>
                                                <Label htmlFor="worker_membership_id">
                                                    Assign worker when accepted
                                                </Label>
                                                <select
                                                    id="worker_membership_id"
                                                    name="worker_membership_id"
                                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                                >
                                                    <option value="">
                                                        Leave in Program queue
                                                    </option>
                                                    {workers.map(
                                                        (worker: any) => (
                                                            <option
                                                                key={worker.id}
                                                                value={
                                                                    worker.id
                                                                }
                                                            >
                                                                {worker.name}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </div>
                                            <div>
                                                <Label htmlFor="reason">
                                                    Decision reason code
                                                </Label>
                                                <select
                                                    id="reason"
                                                    name="reason"
                                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                                >
                                                    <option value="">
                                                        Not required
                                                    </option>
                                                    <option value="client_request">
                                                        Client request
                                                    </option>
                                                    <option value="eligibility">
                                                        Eligibility
                                                    </option>
                                                    <option value="capacity">
                                                        Program capacity
                                                    </option>
                                                    <option value="external_referral">
                                                        External referral
                                                    </option>
                                                    <option value="duplicate">
                                                        Duplicate request
                                                    </option>
                                                    <option value="other">
                                                        Other coded reason
                                                    </option>
                                                </select>
                                            </div>
                                            <Button disabled={processing}>
                                                Apply transition
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <p className="text-muted-foreground text-sm">
                                    No further transition is available to your
                                    role.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
                <Card>
                    <CardHeader>
                        <CardTitle>Duplicate review</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {intake.duplicateReviews.map((review: any) => (
                            <div
                                key={review.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3 text-sm"
                            >
                                <div>
                                    <strong>
                                        {review.candidate.displayName}
                                    </strong>
                                    <p className="text-muted-foreground">
                                        Exact tenant-local match:{' '}
                                        {review.matchedFields.join(', ')}
                                    </p>
                                    <Badge variant="outline">
                                        {review.reversedAt
                                            ? 'reversed'
                                            : review.decision}
                                    </Badge>
                                </div>
                                {canTransition &&
                                review.decision === 'pending' ? (
                                    <div className="flex gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    reviewDuplicate.url([
                                                        organisation.slug,
                                                        review.id,
                                                    ]),
                                                    { decision: 'dismissed' },
                                                )
                                            }
                                        >
                                            Keep separate
                                        </Button>
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    reviewDuplicate.url([
                                                        organisation.slug,
                                                        review.id,
                                                    ]),
                                                    { decision: 'merged' },
                                                )
                                            }
                                        >
                                            Use existing Party
                                        </Button>
                                    </div>
                                ) : null}
                                {canTransition &&
                                review.decision === 'merged' &&
                                !review.reversedAt ? (
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() =>
                                            router.delete(
                                                reverseDuplicate.url([
                                                    organisation.slug,
                                                    review.id,
                                                ]),
                                            )
                                        }
                                    >
                                        Reverse
                                    </Button>
                                ) : null}
                            </div>
                        ))}
                        {intake.duplicateReviews.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                No exact contact match was found in this
                                Organisation.
                            </p>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Case assignment history</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {intake.case?.assignments.map((assignment: any) => (
                            <div key={assignment.id} className="text-sm">
                                <strong>{assignment.worker}</strong> ·{' '}
                                {assignment.status} ·{' '}
                                {new Date(
                                    assignment.startedAt,
                                ).toLocaleString()}
                            </div>
                        ))}
                        {intake.case && canTransition ? (
                            <Form
                                action={assign.url(args)}
                                method="post"
                                className="flex flex-wrap gap-2"
                            >
                                <select
                                    name="membership_id"
                                    className="h-9 rounded-md border bg-transparent px-3"
                                >
                                    {workers.map((worker: any) => (
                                        <option
                                            key={worker.id}
                                            value={worker.id}
                                        >
                                            {worker.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    name="reason"
                                    className="h-9 flex-1 rounded-md border bg-transparent px-3"
                                >
                                    <option value="initial_assignment">
                                        Initial assignment
                                    </option>
                                    <option value="caseload_transfer">
                                        Caseload transfer
                                    </option>
                                    <option value="availability">
                                        Worker availability
                                    </option>
                                    <option value="program_rebalance">
                                        Program rebalance
                                    </option>
                                    <option value="other">
                                        Other coded reason
                                    </option>
                                </select>
                                <Button>Assign or transfer</Button>
                            </Form>
                        ) : null}
                        {!intake.case ? (
                            <p className="text-muted-foreground text-sm">
                                A case is created atomically when this request
                                is accepted.
                            </p>
                        ) : null}
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardTitle>Immutable transition history</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-2 text-sm">
                        {intake.transitions.map((item: any) => (
                            <p key={item.id}>
                                v{item.version}: {item.from ?? 'created'} →{' '}
                                {item.to}
                                {item.reason ? ` — ${item.reason}` : ''}
                            </p>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
