import { Form, Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { activate, index, store } from '@/routes/message-templates';

type MessageTemplate = {
    id: string;
    key: string;
    name: string;
    version: number;
    status: string;
    channel: 'email' | 'sms';
    subject: string;
    body: string;
    journeyKind: string;
    activatedAt: string | null;
};

type JourneyKind = { value: string; label: string };

const sampleValues: Record<string, string> = {
    supporter_name: 'Amina',
    donation_count: '3',
    activity_frequency: '5',
    activity_value: '18 hours',
};

function preview(template: string) {
    return Object.entries(sampleValues).reduce(
        (message, [variable, value]) =>
            message.replaceAll(`{{ ${variable} }}`, value),
        template,
    );
}

export default function MessageTemplatesIndex({
    templates,
    journeyKinds,
}: {
    templates: MessageTemplate[];
    journeyKinds: JourneyKind[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    const form = useForm({
        template_key: '',
        name: '',
        channel: 'email' as 'email' | 'sms',
        subject: '',
        body: 'Hello {{ supporter_name }},',
        journey_kind: journeyKinds[0]?.value ?? 'general',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(organisation.slug), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const useAsStartingPoint = (template: MessageTemplate) => {
        form.setData({
            template_key: template.key,
            name: template.name,
            channel: template.channel,
            subject: template.subject,
            body: template.body,
            journey_kind: template.journeyKind,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <div className="space-y-6 p-4">
            <Head title="Message templates" />
            <Heading
                title="Message templates"
                description="Create reusable email and SMS messages, preview them with example data, then activate the exact version journeys should use."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Create a template draft</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-6 lg:grid-cols-2"
                        onSubmit={submit}
                    >
                        <div className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="template-name">
                                    Template name
                                </Label>
                                <Input
                                    id="template-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData({
                                            ...form.data,
                                            template_key: '',
                                            name: event.target.value,
                                        })
                                    }
                                    placeholder="Donor re-engagement"
                                    required
                                />
                                <InputError message={form.errors.name} />
                                <p className="text-muted-foreground text-xs">
                                    Reusing a name creates the next immutable
                                    version. Changing the name after choosing a
                                    starting point creates a separate template.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="template-channel">
                                        Channel
                                    </Label>
                                    <select
                                        id="template-channel"
                                        className="h-9 rounded-md border bg-transparent px-3"
                                        value={form.data.channel}
                                        onChange={(event) =>
                                            form.setData(
                                                'channel',
                                                event.target.value as
                                                    | 'email'
                                                    | 'sms',
                                            )
                                        }
                                    >
                                        <option value="email">Email</option>
                                        <option value="sms">SMS</option>
                                    </select>
                                    <InputError message={form.errors.channel} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="template-journey-kind">
                                        Journey kind
                                    </Label>
                                    <select
                                        id="template-journey-kind"
                                        className="h-9 rounded-md border bg-transparent px-3"
                                        value={form.data.journey_kind}
                                        onChange={(event) =>
                                            form.setData(
                                                'journey_kind',
                                                event.target.value,
                                            )
                                        }
                                    >
                                        {journeyKinds.map((kind) => (
                                            <option
                                                key={kind.value}
                                                value={kind.value}
                                            >
                                                {kind.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.journey_kind}
                                    />
                                </div>
                            </div>
                            {form.data.channel === 'email' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="template-subject">
                                        Subject
                                    </Label>
                                    <Input
                                        id="template-subject"
                                        value={form.data.subject}
                                        onChange={(event) =>
                                            form.setData(
                                                'subject',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError message={form.errors.subject} />
                                </div>
                            ) : null}
                            <div className="grid gap-2">
                                <Label htmlFor="template-body">Message</Label>
                                <Textarea
                                    id="template-body"
                                    rows={8}
                                    value={form.data.body}
                                    onChange={(event) =>
                                        form.setData('body', event.target.value)
                                    }
                                    required
                                />
                                <div className="text-muted-foreground flex justify-between gap-3 text-xs">
                                    <span>
                                        Variables: supporter_name,
                                        donation_count, activity_frequency,
                                        activity_value
                                    </span>
                                    <span>
                                        {form.data.body.length}/
                                        {form.data.channel === 'sms'
                                            ? '480'
                                            : '4000'}
                                    </span>
                                </div>
                                <InputError message={form.errors.body} />
                            </div>
                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Creating draft…'
                                    : 'Create draft'}
                            </Button>
                        </div>

                        <div className="bg-muted/40 rounded-xl border p-5">
                            <div className="mb-4 flex items-center justify-between gap-2">
                                <strong>Preview</strong>
                                <Badge variant="outline">
                                    {form.data.channel.toUpperCase()}
                                </Badge>
                            </div>
                            {form.data.channel === 'email' ? (
                                <p className="border-b pb-3 font-semibold">
                                    {preview(form.data.subject) ||
                                        'Email subject'}
                                </p>
                            ) : null}
                            <p className="mt-4 whitespace-pre-wrap">
                                {preview(form.data.body)}
                            </p>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Version history</h2>
                {templates.map((template) => (
                    <Card key={template.id}>
                        <CardContent className="grid gap-4 pt-6 lg:grid-cols-[1fr_auto]">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <strong>
                                        {template.name} · v{template.version}
                                    </strong>
                                    <Badge>{template.status}</Badge>
                                    <Badge variant="outline">
                                        {template.channel.toUpperCase()}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {template.journeyKind.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </Badge>
                                </div>
                                {template.channel === 'email' ? (
                                    <p className="font-semibold">
                                        {preview(template.subject)}
                                    </p>
                                ) : null}
                                <p className="text-muted-foreground text-sm whitespace-pre-wrap">
                                    {preview(template.body)}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-start gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => useAsStartingPoint(template)}
                                >
                                    New version
                                </Button>
                                {template.status === 'draft' ? (
                                    <Form
                                        {...activate.form([
                                            organisation.slug,
                                            template.id,
                                        ])}
                                    >
                                        <Button>Activate</Button>
                                    </Form>
                                ) : null}
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </section>
        </div>
    );
}

MessageTemplatesIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Message templates',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
