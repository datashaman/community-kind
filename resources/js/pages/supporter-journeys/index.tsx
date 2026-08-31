import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, show, store } from '@/routes/supporter-journeys';

type Props = {
    journeys: Array<{
        id: string;
        name: string;
        status: string;
        recipientCount: number;
    }>;
    segments: Array<{ id: string; name: string }>;
};

export default function SupporterJourneysIndex({ journeys, segments }: Props) {
    const organisation = usePage().props.currentOrganisation!;

    return (
        <>
            <Head title="Welcome journeys" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Welcome journeys"
                    description="Personalise and simulate supporter acknowledgements locally. No real message is sent."
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Create a local journey</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form(organisation.slug)}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <Field
                                        name="name"
                                        label="Journey name"
                                        error={errors.name}
                                    />
                                    <label className="grid gap-1">
                                        <span>Saved audience</span>
                                        <select
                                            name="audience_segment_id"
                                            required
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            {segments.map((segment) => (
                                                <option
                                                    key={segment.id}
                                                    value={segment.id}
                                                >
                                                    {segment.name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={errors.audience_segment_id}
                                        />
                                    </label>
                                    <Field
                                        name="subject"
                                        label="Subject"
                                        error={errors.subject}
                                    />
                                    <label className="grid gap-1">
                                        <span>Message</span>
                                        <textarea
                                            name="body"
                                            required
                                            rows={6}
                                            defaultValue="Hi {{ supporter_name }}, thank you for supporting our work. Your {{ donation_count }} successful contribution(s) make a difference."
                                            className="rounded-md border bg-transparent px-3 py-2"
                                        />
                                        <InputError message={errors.body} />
                                    </label>
                                    <p className="text-muted-foreground text-sm">
                                        Safe placeholders:{' '}
                                        {'{{ supporter_name }}'} and{' '}
                                        {'{{ donation_count }}'}.
                                    </p>
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || segments.length === 0
                                        }
                                    >
                                        Create and preview
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <section className="space-y-3" aria-labelledby="journey-list">
                    <h2 id="journey-list" className="text-xl font-semibold">
                        Journeys
                    </h2>
                    {journeys.map((journey) => (
                        <Link
                            key={journey.id}
                            href={show([organisation.slug, journey.id])}
                            className="hover:bg-muted/50 block rounded-lg border p-4"
                        >
                            <strong>{journey.name}</strong>
                            <p className="text-muted-foreground text-sm">
                                {journey.status} · {journey.recipientCount}{' '}
                                recipients
                            </p>
                        </Link>
                    ))}
                </section>
            </div>
        </>
    );
}

function Field({
    name,
    label,
    error,
}: {
    name: string;
    label: string;
    error?: string;
}) {
    return (
        <label className="grid gap-1">
            <span>{label}</span>
            <input
                name={name}
                required
                className="h-9 rounded-md border bg-transparent px-3"
            />
            <InputError message={error} />
        </label>
    );
}

SupporterJourneysIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Welcome journeys',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
