import { Form, Head, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, LockKeyhole } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { activate, index, store } from '@/routes/public-forms';

type FormField = {
    key: string;
    label: string;
    type: string;
    required: boolean;
    fixedRequired: boolean;
};

type Purpose = {
    value: string;
    label: string;
    description: string;
    fields: FormField[];
};

type PublicFormVersion = {
    id: string;
    purpose: string;
    purposeLabel: string;
    version: number;
    status: string;
    fields: FormField[];
    activatedAt: string | null;
    canActivate: boolean;
};

function FieldPreview({ field }: { field: FormField }) {
    return (
        <label className="grid gap-1.5">
            <span className="text-sm font-medium">
                {field.label}
                {field.required ? (
                    <span className="text-destructive"> *</span>
                ) : null}
            </span>
            {field.type === 'textarea' ? (
                <textarea
                    className="bg-background min-h-20 rounded-md border px-3 py-2"
                    disabled
                />
            ) : field.type === 'multiselect' ? (
                <select
                    className="bg-background h-20 rounded-md border px-3"
                    disabled
                    multiple
                >
                    <option>Example option</option>
                </select>
            ) : (
                <input
                    className="bg-background h-9 rounded-md border px-3"
                    disabled
                    placeholder={
                        field.type === 'currency'
                            ? 'ZAR'
                            : field.type === 'money'
                              ? '0.00'
                              : undefined
                    }
                    type={
                        field.type === 'money' || field.type === 'number'
                            ? 'number'
                            : field.type
                    }
                />
            )}
        </label>
    );
}

export default function PublicFormsIndex({
    purposes,
    forms,
}: {
    purposes: Purpose[];
    forms: PublicFormVersion[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    const initialPurpose = purposes[0];
    const editor = useForm({
        form: initialPurpose?.value ?? '',
        ordered_fields: initialPurpose?.fields.map((field) => field.key) ?? [],
        required_fields:
            initialPurpose?.fields
                .filter((field) => field.required)
                .map((field) => field.key) ?? [],
    });
    const selectedPurpose = purposes.find(
        (purpose) => purpose.value === editor.data.form,
    );
    const catalogue = new Map(
        selectedPurpose?.fields.map((field) => [field.key, field]) ?? [],
    );
    const orderedFields = editor.data.ordered_fields
        .map((key) => catalogue.get(key))
        .filter((field): field is FormField => field !== undefined)
        .map((field) => ({
            ...field,
            required:
                field.fixedRequired ||
                editor.data.required_fields.includes(field.key),
        }));

    const selectPurpose = (purposeValue: string) => {
        const purpose = purposes.find(
            (candidate) => candidate.value === purposeValue,
        );
        editor.setData({
            form: purposeValue,
            ordered_fields: purpose?.fields.map((field) => field.key) ?? [],
            required_fields:
                purpose?.fields
                    .filter((field) => field.required)
                    .map((field) => field.key) ?? [],
        });
        editor.clearErrors();
    };

    const move = (indexToMove: number, direction: -1 | 1) => {
        const destination = indexToMove + direction;
        if (
            destination < 0 ||
            destination >= editor.data.ordered_fields.length
        ) {
            return;
        }
        const reordered = [...editor.data.ordered_fields];
        [reordered[indexToMove], reordered[destination]] = [
            reordered[destination],
            reordered[indexToMove],
        ];
        editor.setData('ordered_fields', reordered);
    };

    const toggleRequired = (key: string, checked: boolean) => {
        editor.setData(
            'required_fields',
            checked
                ? [...editor.data.required_fields, key]
                : editor.data.required_fields.filter(
                      (candidate) => candidate !== key,
                  ),
        );
    };

    const useAsStartingPoint = (form: PublicFormVersion) => {
        editor.setData({
            form: form.purpose,
            ordered_fields: form.fields.map((field) => field.key),
            required_fields: form.fields
                .filter((field) => field.required)
                .map((field) => field.key),
        });
        editor.clearErrors();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        editor.post(store.url(organisation.slug), {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-6 p-4">
            <Head title="Public forms" />
            <Heading
                title="Public forms"
                description="Arrange supported fields, choose optional requirements, preview the result, and publish an immutable version—without editing JSON."
            />

            <Card>
                <CardHeader>
                    <CardTitle>Create a public form draft</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(20rem,0.85fr)]"
                        onSubmit={submit}
                    >
                        <div className="space-y-5">
                            <div className="grid gap-2">
                                <Label htmlFor="form-purpose">
                                    Form purpose
                                </Label>
                                <select
                                    id="form-purpose"
                                    className="h-9 rounded-md border bg-transparent px-3"
                                    value={editor.data.form}
                                    onChange={(event) =>
                                        selectPurpose(event.target.value)
                                    }
                                >
                                    {purposes.map((purpose) => (
                                        <option
                                            key={purpose.value}
                                            value={purpose.value}
                                        >
                                            {purpose.label}
                                        </option>
                                    ))}
                                </select>
                                <p className="text-muted-foreground text-sm">
                                    {selectedPurpose?.description}
                                </p>
                                <InputError message={editor.errors.form} />
                            </div>

                            <fieldset className="space-y-3">
                                <legend className="font-medium">
                                    Fields and order
                                </legend>
                                <p className="text-muted-foreground text-sm">
                                    All supported fields stay present. Reorder
                                    them and choose whether configurable fields
                                    must be completed.
                                </p>
                                {orderedFields.map((field, fieldIndex) => (
                                    <div
                                        key={field.key}
                                        className="flex flex-wrap items-center gap-3 rounded-lg border p-3"
                                    >
                                        <div className="min-w-36 flex-1">
                                            <div className="font-medium">
                                                {field.label}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {field.type}
                                            </div>
                                        </div>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={field.required}
                                                disabled={field.fixedRequired}
                                                onChange={(event) =>
                                                    toggleRequired(
                                                        field.key,
                                                        event.target.checked,
                                                    )
                                                }
                                            />
                                            Required
                                            {field.fixedRequired ? (
                                                <LockKeyhole
                                                    aria-label="Required by the submission workflow"
                                                    className="size-3.5"
                                                />
                                            ) : null}
                                        </label>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                disabled={fieldIndex === 0}
                                                aria-label={`Move ${field.label} up`}
                                                onClick={() =>
                                                    move(fieldIndex, -1)
                                                }
                                            >
                                                <ArrowUp />
                                            </Button>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                disabled={
                                                    fieldIndex ===
                                                    orderedFields.length - 1
                                                }
                                                aria-label={`Move ${field.label} down`}
                                                onClick={() =>
                                                    move(fieldIndex, 1)
                                                }
                                            >
                                                <ArrowDown />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                                <InputError
                                    message={editor.errors.ordered_fields}
                                />
                                <InputError
                                    message={editor.errors.required_fields}
                                />
                            </fieldset>

                            <Button disabled={editor.processing}>
                                {editor.processing
                                    ? 'Creating draft…'
                                    : 'Create public form draft'}
                            </Button>
                        </div>

                        <div className="bg-muted/40 space-y-4 rounded-xl border p-5">
                            <div>
                                <strong>Preview</strong>
                                <p className="text-muted-foreground text-sm">
                                    {selectedPurpose?.label}
                                </p>
                            </div>
                            <div className="bg-background grid gap-4 rounded-lg border p-4">
                                {orderedFields.map((field) => (
                                    <FieldPreview
                                        key={field.key}
                                        field={field}
                                    />
                                ))}
                                <Button type="button" disabled>
                                    Submit
                                </Button>
                            </div>
                            <p className="text-muted-foreground text-xs">
                                Consent controls remain owned by the public
                                workflow and cannot be changed here.
                            </p>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <section className="space-y-3">
                <h2 className="text-xl font-semibold">Version history</h2>
                {forms.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No public form versions yet. Built-in form behavior
                        remains in place until a draft is activated.
                    </p>
                ) : null}
                {forms.map((form) => (
                    <Card key={form.id}>
                        <CardContent className="grid gap-4 pt-6 lg:grid-cols-[1fr_auto]">
                            <div className="space-y-3">
                                <div className="flex flex-wrap items-center gap-2">
                                    <strong>
                                        {form.purposeLabel} · v{form.version}
                                    </strong>
                                    <Badge>{form.status}</Badge>
                                </div>
                                <ol className="grid gap-1 text-sm sm:grid-cols-2">
                                    {form.fields.map((field, fieldIndex) => (
                                        <li key={field.key}>
                                            {fieldIndex + 1}. {field.label}
                                            {field.required
                                                ? ' · required'
                                                : ' · optional'}
                                        </li>
                                    ))}
                                </ol>
                            </div>
                            <div className="flex flex-wrap items-start gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => useAsStartingPoint(form)}
                                >
                                    New version
                                </Button>
                                {form.canActivate ? (
                                    <Form
                                        {...activate.form([
                                            organisation.slug,
                                            form.id,
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

PublicFormsIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Public forms',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
