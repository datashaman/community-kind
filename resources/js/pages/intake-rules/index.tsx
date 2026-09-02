import { Form, Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { activate, index, store } from '@/routes/intake-rules';

type IntakeRule = {
    id: string;
    version: number;
    status: string;
    requiredFields: string[];
    defaultUrgency: string;
    restrictedAccessBypassAllowed: boolean;
    activatedAt: string | null;
    canActivate: boolean;
};

type Option = { value: string; label: string };

const contactFields: Option[] = [
    { value: 'email', label: 'Email address' },
    { value: 'telephone', label: 'Telephone number' },
];

export default function IntakeRulesIndex({
    rules,
    fixedRequiredFields,
    urgencies,
}: {
    rules: IntakeRule[];
    fixedRequiredFields: Option[];
    urgencies: Option[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    const form = useForm({
        required_contact_fields: [] as string[],
        default_urgency: 'routine',
        allow_restricted_access_bypass: false,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(organisation.slug), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const toggleContact = (field: string, checked: boolean) => {
        form.setData(
            'required_contact_fields',
            checked
                ? [...form.data.required_contact_fields, field]
                : form.data.required_contact_fields.filter(
                      (candidate) => candidate !== field,
                  ),
        );
    };

    const useAsStartingPoint = (rule: IntakeRule) => {
        form.setData({
            required_contact_fields: contactFields
                .map((field) => field.value)
                .filter((field) => rule.requiredFields.includes(field)),
            default_urgency: rule.defaultUrgency,
            allow_restricted_access_bypass: false,
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    return (
        <div className="space-y-6 p-4">
            <Head title="Intake rules" />
            <Heading
                title="Intake rules"
                description="Choose organisation-wide intake defaults while keeping identity, service context, and restricted-access protections fixed."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Create an intake rules draft</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-6 lg:grid-cols-2"
                        onSubmit={submit}
                    >
                        <div className="space-y-5">
                            <fieldset className="space-y-3">
                                <legend className="font-medium">
                                    Required contact details
                                </legend>
                                <p className="text-muted-foreground text-sm">
                                    Choose which contact details staff must
                                    record before an intake can be accepted.
                                </p>
                                {contactFields.map((field) => (
                                    <label
                                        key={field.value}
                                        className="flex items-center gap-2 rounded-lg border p-3"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.data.required_contact_fields.includes(
                                                field.value,
                                            )}
                                            onChange={(event) =>
                                                toggleContact(
                                                    field.value,
                                                    event.target.checked,
                                                )
                                            }
                                        />
                                        {field.label}
                                    </label>
                                ))}
                                <InputError
                                    message={
                                        form.errors.required_contact_fields
                                    }
                                />
                            </fieldset>

                            <div className="grid gap-2">
                                <Label htmlFor="default-urgency">
                                    Default urgency
                                </Label>
                                <select
                                    id="default-urgency"
                                    className="h-9 rounded-md border bg-transparent px-3"
                                    value={form.data.default_urgency}
                                    onChange={(event) =>
                                        form.setData(
                                            'default_urgency',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {urgencies.map((urgency) => (
                                        <option
                                            key={urgency.value}
                                            value={urgency.value}
                                        >
                                            {urgency.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError
                                    message={form.errors.default_urgency}
                                />
                            </div>

                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Creating draft…'
                                    : 'Create intake rules draft'}
                            </Button>
                        </div>

                        <div className="bg-muted/40 space-y-4 rounded-xl border p-5">
                            <strong>Fixed safeguards and context</strong>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {fixedRequiredFields.map((field) => (
                                    <div
                                        key={field.value}
                                        className="bg-background rounded-lg border p-3 text-sm"
                                    >
                                        <span aria-hidden="true">✓ </span>
                                        {field.label} required
                                    </div>
                                ))}
                            </div>
                            {/*
                             * This was a disabled, permanently unchecked
                             * checkbox. Nothing could ever tick it, and an
                             * empty box reads as "safeguard off" when it means
                             * the exact opposite.
                             */}
                            <div className="bg-background rounded-lg border p-4 text-sm">
                                <p className="font-medium">
                                    <span aria-hidden="true">✓ </span>
                                    Restricted-access bypass unavailable
                                </p>
                                <p className="text-muted-foreground mt-2">
                                    Intake settings cannot weaken restricted
                                    case access or staff authorization.
                                </p>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Version history</h2>
                {rules.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No intake rules versions yet. Platform defaults remain
                        active until a draft is activated.
                    </p>
                ) : null}
                {rules.map((rule) => (
                    <Card key={rule.id}>
                        <CardContent className="grid gap-4 pt-6 lg:grid-cols-[1fr_auto]">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <strong>
                                        Intake rules · v{rule.version}
                                    </strong>
                                    <Badge>{rule.status}</Badge>
                                    <Badge variant="outline">
                                        {rule.defaultUrgency} urgency
                                    </Badge>
                                </div>
                                <p className="text-sm">
                                    Required contact details:{' '}
                                    {contactFields
                                        .filter((field) =>
                                            rule.requiredFields.includes(
                                                field.value,
                                            ),
                                        )
                                        .map((field) => field.label)
                                        .join(', ') || 'None'}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    Party identity, program, referral source,
                                    narrative, and presenting needs remain
                                    required. Restricted-access bypass remains
                                    disabled.
                                </p>
                            </div>
                            <div className="flex flex-wrap items-start gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => useAsStartingPoint(rule)}
                                >
                                    New version
                                </Button>
                                {rule.canActivate ? (
                                    <Form
                                        {...activate.form([
                                            organisation.slug,
                                            rule.id,
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

IntakeRulesIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Intake rules',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
