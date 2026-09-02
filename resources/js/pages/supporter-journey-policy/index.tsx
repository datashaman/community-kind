import { Form, Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { activate, index, store } from '@/routes/supporter-journey-policy';

type JourneyPolicy = {
    id: string;
    version: number;
    status: string;
    defaultKind: string;
    defaultChannel: 'email' | 'sms';
    defaultMessageTemplateId: string | null;
    requireApproval: boolean;
    dispatchRechecksConsent: boolean;
    frequencyCapDays: number;
    activatedAt: string | null;
    canActivate: boolean;
};

type MessageTemplate = {
    id: string;
    key: string;
    name: string;
    version: number;
    status: string;
    channel: 'email' | 'sms';
    journeyKind: string;
};

type JourneyKind = { value: string; label: string };

export default function SupporterJourneyPolicyIndex({
    policies,
    templates,
    journeyKinds,
    minimumFrequencyCapDays,
}: {
    policies: JourneyPolicy[];
    templates: MessageTemplate[];
    journeyKinds: JourneyKind[];
    minimumFrequencyCapDays: number;
}) {
    const organisation = usePage().props.currentOrganisation!;
    const form = useForm({
        default_kind: journeyKinds[0]?.value ?? 'general',
        default_channel: 'email' as 'email' | 'sms',
        default_message_template_id: '',
        require_approval: true,
        dispatch_rechecks_consent: true,
        frequency_cap_days: minimumFrequencyCapDays,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(organisation.slug), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const selectTemplate = (id: string) => {
        const template = templates.find((candidate) => candidate.id === id);
        form.setData({
            ...form.data,
            default_message_template_id: id,
            ...(template
                ? {
                      default_kind: template.journeyKind,
                      default_channel: template.channel,
                  }
                : {}),
        });
    };

    const useAsStartingPoint = (policy: JourneyPolicy) => {
        form.setData({
            default_kind: policy.defaultKind,
            default_channel: policy.defaultChannel,
            default_message_template_id: policy.defaultMessageTemplateId ?? '',
            require_approval: true,
            dispatch_rechecks_consent: true,
            frequency_cap_days: policy.frequencyCapDays,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const templateName = (id: string | null) => {
        const template = templates.find((candidate) => candidate.id === id);

        return template
            ? `${template.name} · v${template.version}`
            : 'No default template';
    };

    return (
        <div className="space-y-6 p-4">
            <Head title="Supporter journey policy" />
            <Heading
                title="Supporter journey policy"
                description="Set safe defaults for new supporter journeys. Every change creates an immutable draft that must be explicitly activated."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Create a policy draft</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-6 lg:grid-cols-2"
                        onSubmit={submit}
                    >
                        <div className="space-y-5">
                            <div className="grid gap-2">
                                <Label htmlFor="policy-template">
                                    Default message template
                                </Label>
                                <select
                                    id="policy-template"
                                    className="h-9 rounded-md border bg-transparent px-3"
                                    value={
                                        form.data.default_message_template_id
                                    }
                                    onChange={(event) =>
                                        selectTemplate(event.target.value)
                                    }
                                >
                                    <option value="">
                                        No default — choose for each journey
                                    </option>
                                    {templates.map((template) => (
                                        <option
                                            key={template.id}
                                            value={template.id}
                                        >
                                            {template.name} · v
                                            {template.version} ·{' '}
                                            {template.channel.toUpperCase()} ·{' '}
                                            {template.status}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={
                                        form.errors.default_message_template_id
                                    }
                                />
                                <p className="text-muted-foreground text-xs">
                                    Every previously activated version is
                                    available. Selecting one also matches the
                                    journey kind and channel.
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="policy-kind">
                                        Default journey kind
                                    </Label>
                                    <select
                                        id="policy-kind"
                                        className="h-9 rounded-md border bg-transparent px-3"
                                        value={form.data.default_kind}
                                        onChange={(event) =>
                                            form.setData({
                                                ...form.data,
                                                default_kind:
                                                    event.target.value,
                                                default_message_template_id: '',
                                            })
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
                                        message={form.errors.default_kind}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="policy-channel">
                                        Default channel
                                    </Label>
                                    <select
                                        id="policy-channel"
                                        className="h-9 rounded-md border bg-transparent px-3"
                                        value={form.data.default_channel}
                                        onChange={(event) =>
                                            form.setData({
                                                ...form.data,
                                                default_channel: event.target
                                                    .value as 'email' | 'sms',
                                                default_message_template_id: '',
                                            })
                                        }
                                    >
                                        <option value="email">Email</option>
                                        <option value="sms">SMS</option>
                                    </select>
                                    <InputError
                                        message={form.errors.default_channel}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="policy-frequency-cap">
                                    Contact frequency cap (days)
                                </Label>
                                <Input
                                    id="policy-frequency-cap"
                                    type="number"
                                    min={minimumFrequencyCapDays}
                                    max={365}
                                    value={form.data.frequency_cap_days}
                                    onChange={(event) =>
                                        form.setData(
                                            'frequency_cap_days',
                                            Number(event.target.value),
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.frequency_cap_days}
                                />
                                <p className="text-muted-foreground text-xs">
                                    A supporter who received another journey
                                    within this period is excluded. The platform
                                    minimum is {minimumFrequencyCapDays} days.
                                </p>
                            </div>

                            <input
                                type="hidden"
                                name="require_approval"
                                value="1"
                            />
                            <input
                                type="hidden"
                                name="dispatch_rechecks_consent"
                                value="1"
                            />
                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Creating draft…'
                                    : 'Create policy draft'}
                            </Button>
                        </div>

                        <div className="bg-muted/40 space-y-4 rounded-xl border p-5">
                            <strong>Safeguards</strong>
                            <Safeguard
                                title="Approval required"
                                description="The audience and message must be reviewed and frozen before scheduling or dispatch."
                            />
                            <Safeguard
                                title="Consent rechecked at dispatch"
                                description="People who withdraw consent after approval are excluded before any message is simulated."
                            />
                            <p className="text-muted-foreground text-xs">
                                These protections are mandatory and cannot be
                                disabled by organisation policy.
                            </p>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Version history</h2>
                {policies.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No policy versions yet. Platform defaults remain in
                        effect until a draft is activated.
                    </p>
                ) : null}
                {policies.map((policy) => (
                    <Card key={policy.id}>
                        <CardContent className="grid gap-4 pt-6 lg:grid-cols-[1fr_auto]">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <strong>
                                        Journey policy · v{policy.version}
                                    </strong>
                                    <Badge>{policy.status}</Badge>
                                    <Badge variant="outline">
                                        {policy.defaultChannel.toUpperCase()}
                                    </Badge>
                                    <Badge variant="secondary">
                                        {policy.defaultKind.replaceAll(
                                            '_',
                                            ' ',
                                        )}
                                    </Badge>
                                </div>
                                <dl className="grid gap-2 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Default template
                                        </dt>
                                        <dd>
                                            {templateName(
                                                policy.defaultMessageTemplateId,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Contact cap
                                        </dt>
                                        <dd>{policy.frequencyCapDays} days</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Approval
                                        </dt>
                                        <dd>Required</dd>
                                    </div>
                                    <div>
                                        <dt className="text-muted-foreground">
                                            Dispatch consent check
                                        </dt>
                                        <dd>Required</dd>
                                    </div>
                                </dl>
                            </div>
                            <div className="flex flex-wrap items-start gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => useAsStartingPoint(policy)}
                                >
                                    New version
                                </Button>
                                {policy.canActivate ? (
                                    <Form
                                        {...activate.form([
                                            organisation.slug,
                                            policy.id,
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

function Safeguard({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="bg-background rounded-lg border p-4 text-sm">
            {/*
             * A safeguard is a statement of what the platform guarantees, not
             * a setting. It used to be drawn as a disabled checkbox, which no
             * one could operate.
             */}
            <p className="font-medium">
                <span aria-hidden="true">✓ </span>
                {title}
            </p>
            <p className="text-muted-foreground mt-2">{description}</p>
        </div>
    );
}

SupporterJourneyPolicyIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Supporter journey policy',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
