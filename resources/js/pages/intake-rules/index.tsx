import { Form, Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useRef, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type Draft = {
    required_contact_fields: string[];
    default_urgency: string;
};

const contactFields: Option[] = [
    { value: 'email', label: 'Email address' },
    { value: 'telephone', label: 'Telephone number' },
];

const contactSummary = (requiredFields: string[]) =>
    contactFields
        .filter((field) => requiredFields.includes(field.value))
        .map((field) => field.label)
        .join(', ') || 'None';

function DraftPanel({
    initial,
    fixedRequiredFields,
    urgencies,
    onDismiss,
}: {
    initial: Draft;
    fixedRequiredFields: Option[];
    urgencies: Option[];
    onDismiss: () => void;
}) {
    const organisation = usePage().props.currentOrganisation!;
    const form = useForm({
        ...initial,
        allow_restricted_access_bypass: false,
    });
    const firstField = useRef<HTMLInputElement>(null);

    /*
     * The panel is not on the page until it is asked for, so opening it has to
     * take the caret with it, exactly as on impact packs.
     */
    useEffect(() => firstField.current?.focus(), []);

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(store.url(organisation.slug), {
            preserveScroll: true,
            onSuccess: onDismiss,
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

    return (
        <section
            aria-labelledby="intake-rules-draft-heading"
            className="bg-muted/30 max-w-xl space-y-5 rounded-xl border p-5"
        >
            <h2 id="intake-rules-draft-heading" className="font-medium">
                New intake rules version
            </h2>
            <form className="space-y-5" onSubmit={submit}>
                <fieldset className="space-y-2">
                    <legend className="text-sm font-medium">
                        Contact details staff must record
                    </legend>
                    {contactFields.map((field, fieldIndex) => (
                        <label
                            key={field.value}
                            className="flex items-center gap-3 rounded-lg border p-3 text-sm"
                        >
                            <input
                                type="checkbox"
                                ref={fieldIndex === 0 ? firstField : undefined}
                                className="size-4"
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
                    <InputError message={form.errors.required_contact_fields} />
                </fieldset>

                <div className="grid max-w-xs gap-2">
                    <Label htmlFor="default-urgency">Default urgency</Label>
                    <select
                        id="default-urgency"
                        className="h-9 rounded-md border bg-transparent px-3"
                        value={form.data.default_urgency}
                        onChange={(event) =>
                            form.setData('default_urgency', event.target.value)
                        }
                    >
                        {urgencies.map((urgency) => (
                            <option key={urgency.value} value={urgency.value}>
                                {urgency.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.default_urgency} />
                </div>

                <div className="flex gap-2">
                    <Button disabled={form.processing}>
                        {form.processing ? 'Creating draft…' : 'Create draft'}
                    </Button>
                    <Button type="button" variant="ghost" onClick={onDismiss}>
                        Cancel
                    </Button>
                </div>
            </form>

            {/*
             * The fixed material used to occupy half the form and was repeated
             * in the page description and again on every version card. It never
             * varies, so it is stated once, for the person who wonders why the
             * two controls above are the only ones here.
             */}
            <details className="text-sm">
                <summary className="cursor-pointer font-medium">
                    What intake rules cannot change
                </summary>
                <p className="text-muted-foreground mt-2">
                    {fixedRequiredFields.map((field) => field.label).join(', ')}{' '}
                    are always required, whatever this version says. Intake
                    rules also cannot weaken restricted case access or staff
                    authorization.
                </p>
            </details>
        </section>
    );
}

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
    const [draft, setDraft] = useState<Draft | null>(null);
    const newVersionButton = useRef<HTMLButtonElement>(null);
    const returnFocus = useRef(false);

    /*
     * The button is disabled while the panel is open, so it cannot take focus
     * until the panel has gone and that render has landed.
     */
    useEffect(() => {
        if (draft || !returnFocus.current) return;
        returnFocus.current = false;
        newVersionButton.current?.focus();
    }, [draft]);

    const dismiss = () => {
        returnFocus.current = true;
        setDraft(null);
    };

    const startFrom = (rule: IntakeRule | null) =>
        setDraft({
            required_contact_fields: rule
                ? contactFields
                      .map((field) => field.value)
                      .filter((field) => rule.requiredFields.includes(field))
                : [],
            default_urgency: rule?.defaultUrgency ?? 'routine',
        });

    return (
        <div className="space-y-6 p-4">
            <Head title="Intake rules" />
            <div className="flex flex-wrap items-start justify-between gap-4">
                <Heading
                    title="Intake rules"
                    description="Which contact details staff must record at intake, and the urgency a new case starts at."
                />
                <Button
                    ref={newVersionButton}
                    type="button"
                    onClick={() => startFrom(rules[0] ?? null)}
                    disabled={draft !== null}
                >
                    New version
                </Button>
            </div>

            {draft ? (
                <DraftPanel
                    key={JSON.stringify(draft)}
                    initial={draft}
                    fixedRequiredFields={fixedRequiredFields}
                    urgencies={urgencies}
                    onDismiss={dismiss}
                />
            ) : null}

            <section aria-labelledby="intake-rules-versions-heading">
                <h2
                    id="intake-rules-versions-heading"
                    className="mb-3 text-xl font-semibold"
                >
                    Versions
                </h2>
                {rules.length === 0 ? (
                    <div className="bg-muted/30 rounded-xl border border-dashed p-6">
                        <p className="font-medium">No version yet</p>
                        <p className="text-muted-foreground mt-1 max-w-prose text-sm">
                            Platform defaults stay in force until a version is
                            activated: no contact detail is required, and new
                            cases start at routine urgency.
                        </p>
                    </div>
                ) : (
                    <ul className="space-y-3">
                        {rules.map((rule) => (
                            <li
                                key={rule.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border p-4"
                            >
                                <div className="space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <strong>v{rule.version}</strong>
                                        <Badge>{rule.status}</Badge>
                                        <Badge variant="outline">
                                            {rule.defaultUrgency} urgency
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        Required contact details:{' '}
                                        {contactSummary(rule.requiredFields)}
                                    </p>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => startFrom(rule)}
                                    >
                                        Copy to new version
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
                            </li>
                        ))}
                    </ul>
                )}
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
