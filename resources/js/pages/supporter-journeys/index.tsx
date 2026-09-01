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
    policyDefaults: {
        templateId: string | null;
        journeyKind: string;
        channel: string;
    } | null;
    templates: Array<{
        key: string;
        id: string;
        name: string;
        version: number;
        status: string;
        channel: string;
        journeyKind: string;
    }>;
};

export default function SupporterJourneysIndex({
    journeys,
    segments,
    policyDefaults,
    templates,
}: Props) {
    const organisation = usePage().props.currentOrganisation!;
    const defaultTemplate = templates.find(
        (template) => template.id === policyDefaults?.templateId,
    );

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
                                        <span>
                                            Active message template (optional)
                                        </span>
                                        <select
                                            name="message_template_id"
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            <option value="">
                                                {defaultTemplate
                                                    ? `Organisation default: ${defaultTemplate.name}`
                                                    : 'Custom content below'}
                                            </option>
                                            {defaultTemplate ? (
                                                <option value="__custom__">
                                                    Custom content instead
                                                </option>
                                            ) : null}
                                            {templates.map((template) => (
                                                <option
                                                    key={template.id}
                                                    value={template.id}
                                                >
                                                    {template.name} · v
                                                    {template.version} ·{' '}
                                                    {template.channel} ·{' '}
                                                    {template.status} ·{' '}
                                                    {template.journeyKind.replaceAll(
                                                        '_',
                                                        ' ',
                                                    )}
                                                </option>
                                            ))}
                                        </select>
                                        <small className="text-muted-foreground">
                                            Selecting a template uses its frozen
                                            active content and channel.
                                        </small>
                                    </label>
                                    <label className="grid gap-1">
                                        <span>Journey path</span>
                                        <select
                                            name="journey_kind"
                                            defaultValue={
                                                policyDefaults?.journeyKind ??
                                                'general'
                                            }
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            <option value="general">
                                                General
                                            </option>
                                            <option value="re_engagement">
                                                Re-engagement
                                            </option>
                                            <option value="event">Event</option>
                                            <option value="volunteer">
                                                Volunteer
                                            </option>
                                        </select>
                                    </label>
                                    <label className="grid gap-1">
                                        <span>Channel</span>
                                        <select
                                            name="channel"
                                            defaultValue={
                                                policyDefaults?.channel ??
                                                'email'
                                            }
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            <option value="email">Email</option>
                                            <option value="sms">SMS</option>
                                        </select>
                                        <InputError message={errors.channel} />
                                    </label>
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
                                        label="Subject (email only)"
                                        error={errors.subject}
                                        required={false}
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
                                        {'{{ donation_count }}'},{' '}
                                        {'{{ activity_frequency }}'}, and{' '}
                                        {'{{ activity_value }}'}.
                                    </p>
                                    <input
                                        type="hidden"
                                        name="experiment_enabled"
                                        value="0"
                                    />
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="checkbox"
                                            name="experiment_enabled"
                                            value="1"
                                        />
                                        Enable deterministic A/B message variant
                                    </label>
                                    <Field
                                        name="variant_subject"
                                        label="Variant B subject (when experiment enabled)"
                                        error={errors.variant_subject}
                                        required={false}
                                    />
                                    <label className="grid gap-1">
                                        <span>
                                            Variant B message (when experiment
                                            enabled)
                                        </span>
                                        <textarea
                                            name="variant_body"
                                            rows={4}
                                            className="rounded-md border bg-transparent px-3 py-2"
                                        />
                                        <InputError
                                            message={errors.variant_body}
                                        />
                                    </label>
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
    required = true,
}: {
    name: string;
    label: string;
    error?: string;
    required?: boolean;
}) {
    return (
        <label className="grid gap-1">
            <span>{label}</span>
            <input
                name={name}
                required={required}
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
