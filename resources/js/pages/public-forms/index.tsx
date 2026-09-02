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
    version: number;
    status: string;
    fields: FormField[];
    activatedAt: string | null;
    canActivate: boolean;
};

type PublicForm = {
    purpose: string;
    purposeLabel: string;
    activeVersion: number | null;
    versions: PublicFormVersion[];
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

function VersionDetail({
    version,
    organisationSlug,
    onNewVersion,
}: {
    version: PublicFormVersion;
    organisationSlug: string;
    onNewVersion: () => void;
}) {
    const optionalCount = version.fields.filter(
        (field) => !field.required,
    ).length;

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-2">
                    <strong>v{version.version}</strong>
                    <Badge>{version.status}</Badge>
                    {version.activatedAt ? (
                        <time
                            className="text-muted-foreground text-sm"
                            dateTime={version.activatedAt}
                        >
                            activated{' '}
                            {new Date(version.activatedAt).toLocaleDateString()}
                        </time>
                    ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onNewVersion}
                    >
                        New version
                    </Button>
                    {version.canActivate ? (
                        <Form
                            {...activate.form([organisationSlug, version.id])}
                        >
                            <Button>Activate</Button>
                        </Form>
                    ) : null}
                </div>
            </div>
            {/*
             * The fields were a two-column grid, so a list whose whole point is
             * the order the fields appear in read 1,2 then 3,4 across — and
             * stretched two short columns over the full card width. They now
             * run in one wrapped sequence at reading width, in form order.
             *
             * "required" was printed against every field, which says nothing
             * when it is the common case. Only the exception is marked, and the
             * count is stated once.
             */}
            <ol className="text-muted-foreground flex max-w-prose flex-wrap items-center text-sm">
                {version.fields.map((field, fieldIndex) => (
                    <li key={field.key} className="flex items-center">
                        {fieldIndex > 0 ? (
                            <span
                                aria-hidden="true"
                                className="mx-2 opacity-50"
                            >
                                ·
                            </span>
                        ) : null}
                        <span className="text-foreground">{field.label}</span>
                        {field.required ? null : (
                            <span className="ml-1">(optional)</span>
                        )}
                    </li>
                ))}
            </ol>
            <p className="text-muted-foreground text-xs">
                {version.fields.length}{' '}
                {version.fields.length === 1 ? 'field' : 'fields'}
                {optionalCount > 0
                    ? `, ${optionalCount} optional`
                    : ', all required'}
            </p>
        </div>
    );
}

export default function PublicFormsIndex({
    purposes,
    forms,
}: {
    purposes: Purpose[];
    forms: PublicForm[];
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

    const useAsStartingPoint = (
        purpose: string,
        version: PublicFormVersion,
    ) => {
        editor.setData({
            form: purpose,
            ordered_fields: version.fields.map((field) => field.key),
            required_fields: version.fields
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
                description="Arrange fields, choose requirements, preview the result, and publish a version for public use."
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
                <h2 className="text-xl font-semibold">All forms</h2>
                {forms.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        No public forms yet. Built-in form behavior remains in
                        place until a draft is activated.
                    </p>
                ) : null}
                {forms.map((form) => {
                    const [current, ...superseded] = form.versions;

                    return (
                        <Card key={form.purpose}>
                            {/*
                             * The status sits beside the name it describes.
                             * Pushed to the far edge of a full-width card it
                             * read as unrelated to anything.
                             */}
                            <CardHeader className="flex-row flex-wrap items-baseline space-y-0 gap-x-3 gap-y-1">
                                <CardTitle>{form.purposeLabel}</CardTitle>
                                <p className="text-muted-foreground text-sm">
                                    {form.activeVersion === null
                                        ? 'Not published'
                                        : `Live: v${form.activeVersion}`}
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {current ? (
                                    <VersionDetail
                                        version={current}
                                        organisationSlug={organisation.slug}
                                        onNewVersion={() =>
                                            useAsStartingPoint(
                                                form.purpose,
                                                current,
                                            )
                                        }
                                    />
                                ) : null}
                                {superseded.length > 0 ? (
                                    <details className="border-t pt-4">
                                        <summary className="cursor-pointer text-sm font-medium">
                                            {superseded.length} earlier{' '}
                                            {superseded.length === 1
                                                ? 'version'
                                                : 'versions'}
                                        </summary>
                                        <div className="mt-4 space-y-4">
                                            {superseded.map((version) => (
                                                <VersionDetail
                                                    key={version.id}
                                                    version={version}
                                                    organisationSlug={
                                                        organisation.slug
                                                    }
                                                    onNewVersion={() =>
                                                        useAsStartingPoint(
                                                            form.purpose,
                                                            version,
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </details>
                                ) : null}
                            </CardContent>
                        </Card>
                    );
                })}
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
