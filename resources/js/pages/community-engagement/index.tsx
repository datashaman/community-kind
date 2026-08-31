import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { store as storeEvent } from '@/routes/community-engagement/events';
import { store as remindRegistration } from '@/routes/community-engagement/event-registrations/reminder';
import { store as transitionRegistration } from '@/routes/community-engagement/event-registrations/transitions';
import { store as transitionOffer } from '@/routes/community-engagement/in-kind-offers/transitions';
import { store as storePartnerCommitment } from '@/routes/community-engagement/partners/commitments';
import { store as storePartner } from '@/routes/community-engagement/partners';

type EventItem = {
    id: string;
    title: string;
    status: string;
    capacity: number;
    startsAt: string;
    registrations: {
        id: string;
        name: string;
        status: string;
        remindedAt: string | null;
        allowedTransitions: string[];
    }[];
};
type Offer = {
    id: string;
    name: string;
    category: string;
    description: string;
    quantity: string;
    unit: string;
    estimatedValueMinor: number | null;
    currency: string | null;
    condition: string;
    status: string;
    outcome: string | null;
    allowedTransitions: string[];
};
type Partner = {
    id: string;
    name: string;
    type: string;
    status: string;
    relationshipSummary: string;
    email: string | null;
    commitments: {
        id: string;
        title: string;
        details: string;
        status: string;
        dueOn: string | null;
    }[];
};

export default function CommunityEngagementIndex({
    events,
    offers,
    partners,
}: {
    events: EventItem[];
    offers: Offer[];
    partners: Partner[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    const slug = organisation.slug;
    return (
        <div className="space-y-8 p-4">
            <Head title="Community engagement" />
            <Heading
                title="Community engagement"
                description="Manage event attendees, in-kind offers, and community partners without crossing service-data boundaries."
            />
            <Card>
                <CardHeader>
                    <CardTitle>Create event</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        {...storeEvent.form(slug)}
                        className="grid gap-3 md:grid-cols-2"
                    >
                        {({ processing }) => (
                            <>
                                <Input
                                    name="title"
                                    placeholder="Event title"
                                    required
                                />
                                <Input
                                    name="capacity"
                                    type="number"
                                    min="1"
                                    placeholder="Capacity"
                                    required
                                />
                                <Textarea
                                    name="summary"
                                    placeholder="Event summary"
                                    required
                                    className="md:col-span-2"
                                />
                                <Input
                                    name="registration_opens_at"
                                    type="datetime-local"
                                    required
                                />
                                <Input
                                    name="registration_closes_at"
                                    type="datetime-local"
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
                                <select
                                    name="status"
                                    className="h-9 rounded-md border bg-transparent px-3"
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                </select>
                                <Button disabled={processing}>
                                    Create event
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
            <section>
                <h2 className="text-xl font-semibold">Events</h2>
                <div className="mt-3 space-y-4">
                    {events.map((event) => (
                        <Card key={event.id}>
                            <CardHeader>
                                <CardTitle>{event.title}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p>
                                    {new Date(event.startsAt).toLocaleString()}{' '}
                                    · {event.registrations.length}/
                                    {event.capacity}
                                </p>
                                {event.registrations.map((registration) => (
                                    <div
                                        key={registration.id}
                                        className="flex flex-wrap items-center gap-2 rounded border p-3"
                                    >
                                        <span className="font-medium">
                                            {registration.name}
                                        </span>
                                        <Badge>{registration.status}</Badge>
                                        {registration.status === 'confirmed' &&
                                        !registration.remindedAt ? (
                                            <Form
                                                {...remindRegistration.form([
                                                    slug,
                                                    registration.id,
                                                ])}
                                            >
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    Record reminder
                                                </Button>
                                            </Form>
                                        ) : null}
                                        {registration.allowedTransitions
                                            .length > 0 ? (
                                            <Form
                                                {...transitionRegistration.form(
                                                    [slug, registration.id],
                                                )}
                                                className="flex gap-2"
                                            >
                                                <select
                                                    name="status"
                                                    className="h-8 rounded border bg-transparent px-2"
                                                >
                                                    {registration.allowedTransitions.map(
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
                                                <Button size="sm">
                                                    Update
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
            <section>
                <h2 className="text-xl font-semibold">In-kind offers</h2>
                <div className="mt-3 grid gap-4 md:grid-cols-2">
                    {offers.map((offer) => (
                        <Card key={offer.id}>
                            <CardHeader>
                                <CardTitle>
                                    {offer.name} · {offer.category}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p>
                                    {offer.quantity} {offer.unit} ·{' '}
                                    {offer.condition}
                                </p>
                                <p>{offer.description}</p>
                                <Badge>{offer.status}</Badge>
                                {offer.outcome ? (
                                    <p>Outcome: {offer.outcome}</p>
                                ) : null}
                                {offer.allowedTransitions.length > 0 ? (
                                    <Form
                                        {...transitionOffer.form([
                                            slug,
                                            offer.id,
                                        ])}
                                        className="space-y-2"
                                    >
                                        <select
                                            name="status"
                                            className="h-9 w-full rounded border bg-transparent px-3"
                                        >
                                            {offer.allowedTransitions.map(
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
                                        <Textarea
                                            name="fulfilment_outcome"
                                            placeholder="Required for a fulfilment outcome"
                                        />
                                        <Button>Update offer</Button>
                                    </Form>
                                ) : null}
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>
            <Card>
                <CardHeader>
                    <CardTitle>Add partner</CardTitle>
                </CardHeader>
                <CardContent>
                    <Form
                        {...storePartner.form(slug)}
                        className="grid gap-3 md:grid-cols-2"
                    >
                        {({ processing }) => (
                            <>
                                <Input
                                    name="name"
                                    placeholder="Organisation name"
                                    required
                                />
                                <select
                                    name="partner_type"
                                    className="h-9 rounded border bg-transparent px-3"
                                >
                                    <option value="local_business">
                                        Local business
                                    </option>
                                    <option value="community_hub">
                                        Community hub
                                    </option>
                                </select>
                                <Input
                                    name="email"
                                    type="email"
                                    placeholder="Contact email"
                                />
                                <Input
                                    name="telephone"
                                    placeholder="Contact telephone"
                                />
                                <Textarea
                                    name="relationship_summary"
                                    placeholder="Relationship and shared purpose"
                                    required
                                    className="md:col-span-2"
                                />
                                <Button disabled={processing}>
                                    Add partner profile
                                </Button>
                            </>
                        )}
                    </Form>
                </CardContent>
            </Card>
            <section>
                <h2 className="text-xl font-semibold">Partners</h2>
                <div className="mt-3 grid gap-4 md:grid-cols-2">
                    {partners.map((partner) => (
                        <Card key={partner.id}>
                            <CardHeader>
                                <CardTitle>{partner.name}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <p>
                                    {partner.type.replaceAll('_', ' ')} ·{' '}
                                    {partner.email}
                                </p>
                                <p>{partner.relationshipSummary}</p>
                                {partner.commitments.map((commitment) => (
                                    <div
                                        key={commitment.id}
                                        className="rounded border p-3"
                                    >
                                        <p className="font-medium">
                                            {commitment.title}
                                        </p>
                                        <p>{commitment.details}</p>
                                        <Badge>{commitment.status}</Badge>
                                    </div>
                                ))}
                                <Form
                                    {...storePartnerCommitment.form([
                                        slug,
                                        partner.id,
                                    ])}
                                    className="space-y-2"
                                >
                                    <Input
                                        name="title"
                                        placeholder="Commitment"
                                        required
                                    />
                                    <Textarea
                                        name="details"
                                        placeholder="Details"
                                        required
                                    />
                                    <select
                                        name="status"
                                        className="h-9 w-full rounded border bg-transparent px-3"
                                    >
                                        <option value="planned">Planned</option>
                                        <option value="completed">
                                            Completed
                                        </option>
                                        <option value="cancelled">
                                            Cancelled
                                        </option>
                                    </select>
                                    <Input name="due_on" type="date" />
                                    <Button>Record commitment</Button>
                                </Form>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </section>
        </div>
    );
}

CommunityEngagementIndex.layout = () => ({
    breadcrumbs: [{ title: 'Community engagement', href: '#' }],
});
