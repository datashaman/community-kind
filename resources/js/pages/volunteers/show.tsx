import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/volunteers';
import { store as storeCredential } from '@/routes/volunteers/applications/credentials';
import { store as transitionApplication } from '@/routes/volunteers/applications/transitions';
import { store as transitionAssignment } from '@/routes/volunteers/assignments/transitions';
import { store as storeShift } from '@/routes/volunteers/shifts';

type Assignment = {
    id: string;
    status: string;
    shiftTitle: string;
    startsAt: string;
    minutes: number | null;
};
type Application = {
    id: string;
    name: string;
    email: string | null;
    status: string;
    allowedTransitions: string[];
    onboardingStatus: string;
    interests: string[];
    availability: string[];
    followUpStatus: string;
    credentials: {
        id: string;
        type: string;
        status: string;
        expiresAt: string | null;
        expiresSoon: boolean;
    }[];
    assignments: Assignment[];
};
type Opportunity = {
    id: string;
    title: string;
    summary: string;
    status: string;
    capacity: number;
    applications: Application[];
    shifts: {
        id: string;
        title: string;
        startsAt: string;
        endsAt: string;
        capacity: number;
        assignedCount: number;
    }[];
};

export default function VolunteerShow({
    opportunity,
}: {
    opportunity: Opportunity;
}) {
    const organisation = usePage().props.currentOrganisation!;
    const base = [organisation.slug, opportunity.id] as [string, string];
    return (
        <div className="space-y-8 p-4">
            <Head title={opportunity.title} />
            <Link
                href={index(organisation.slug)}
                className="text-muted-foreground text-sm"
            >
                ← Volunteering
            </Link>
            <Heading
                title={opportunity.title}
                description={opportunity.summary}
            />
            <Card>
                <CardHeader>
                    <CardTitle>Add shift</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        {...storeShift.form(base)}
                        className="grid gap-3 md:grid-cols-4"
                    >
                        {({ processing }) => (
                            <>
                                <Input
                                    name="title"
                                    placeholder="Shift title"
                                    required
                                />
                                <Input
                                    name="starts_at"
                                    type="datetime-local"
                                    required
                                />
                                <Input
                                    name="ends_at"
                                    type="datetime-local"
                                    required
                                />
                                <Input
                                    name="capacity"
                                    type="number"
                                    min="1"
                                    placeholder="Capacity"
                                    required
                                />
                                <Button disabled={processing}>Add shift</Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
            <section aria-labelledby="shifts">
                <h2 id="shifts" className="text-xl font-semibold">
                    Shifts
                </h2>
                <div className="mt-3 grid gap-3 md:grid-cols-2">
                    {opportunity.shifts.map((shift) => (
                        <Card key={shift.id}>
                            <CardContent className="pt-5">
                                <p className="font-medium">{shift.title}</p>
                                <p className="text-sm">
                                    {new Date(shift.startsAt).toLocaleString()}
                                </p>
                                <Badge variant="outline">
                                    {shift.assignedCount} / {shift.capacity}{' '}
                                    confirmed
                                </Badge>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>
            <section aria-labelledby="applications">
                <h2 id="applications" className="text-xl font-semibold">
                    Applications
                </h2>
                <div className="mt-3 space-y-4">
                    {opportunity.applications.map((application) => (
                        <Card key={application.id}>
                            <CardHeader>
                                <CardTitle>{application.name}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm">{application.email}</p>
                                <div className="flex flex-wrap gap-2">
                                    <Badge>{application.status}</Badge>
                                    <Badge variant="outline">
                                        Onboarding:{' '}
                                        {application.onboardingStatus}
                                    </Badge>
                                    <Badge variant="outline">
                                        Follow-up: {application.followUpStatus}
                                    </Badge>
                                </div>
                                <p className="text-sm">
                                    Interests:{' '}
                                    {application.interests.join(', ') || 'None'}{' '}
                                    · Availability:{' '}
                                    {application.availability.join(', ')}
                                </p>
                                {application.allowedTransitions.length > 0 ? (
                                    <Form
                                        {...transitionApplication.form([
                                            organisation.slug,
                                            opportunity.id,
                                            application.id,
                                        ])}
                                        className="flex gap-2"
                                    >
                                        <select
                                            name="status"
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            {application.allowedTransitions.map(
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
                                        <Button>Update application</Button>
                                    </Form>
                                ) : null}
                                <Form
                                    {...storeCredential.form([
                                        organisation.slug,
                                        opportunity.id,
                                        application.id,
                                    ])}
                                    className="grid gap-2 md:grid-cols-4"
                                >
                                    <Input
                                        name="type"
                                        placeholder="Check or qualification"
                                        required
                                    />
                                    <select
                                        name="status"
                                        className="h-9 rounded-md border bg-transparent px-3"
                                    >
                                        <option value="pending">Pending</option>
                                        <option value="verified">
                                            Verified
                                        </option>
                                    </select>
                                    <Input name="expires_at" type="date" />
                                    <Button>Record credential</Button>
                                </Form>
                                {application.credentials.map((credential) => (
                                    <p key={credential.id} className="text-sm">
                                        {credential.type}: {credential.status}
                                        {credential.expiresAt
                                            ? ` · expires ${new Date(credential.expiresAt).toLocaleDateString()}`
                                            : ''}
                                        {credential.expiresSoon
                                            ? ' · expires soon'
                                            : ''}
                                    </p>
                                ))}
                                {application.assignments.map((assignment) => (
                                    <div
                                        key={assignment.id}
                                        className="rounded border p-3"
                                    >
                                        <p className="font-medium">
                                            {assignment.shiftTitle}
                                        </p>
                                        <p className="text-sm">
                                            {new Date(
                                                assignment.startsAt,
                                            ).toLocaleString()}{' '}
                                            · {assignment.status}
                                            {assignment.minutes
                                                ? ` · ${assignment.minutes} minutes`
                                                : ''}
                                        </p>
                                        {assignment.status === 'confirmed' ? (
                                            <Form
                                                {...transitionAssignment.form([
                                                    organisation.slug,
                                                    opportunity.id,
                                                    assignment.id,
                                                ])}
                                                className="mt-2 flex gap-2"
                                            >
                                                <select
                                                    name="status"
                                                    className="h-9 rounded-md border bg-transparent px-3"
                                                >
                                                    <option value="attended">
                                                        Attended
                                                    </option>
                                                    <option value="no_show">
                                                        No show
                                                    </option>
                                                    <option value="cancelled">
                                                        Cancelled
                                                    </option>
                                                </select>
                                                <Input
                                                    name="minutes"
                                                    type="number"
                                                    min="1"
                                                    placeholder="Minutes when attended"
                                                />
                                                <Button>
                                                    Record shift outcome
                                                </Button>
                                            </Form>
                                        ) : null}
                                    </div>
                                ))}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>
        </div>
    );
}

VolunteerShow.layout = (props: {
    currentOrganisation: { slug: string };
    opportunity: Opportunity;
}) => ({
    breadcrumbs: [
        { title: 'Volunteering', href: index(props.currentOrganisation.slug) },
        { title: props.opportunity.title, href: '#' },
    ],
});
