import { Form, Head, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { activate, index, store } from '@/routes/organisation-configurations';

type Configuration = {
    id: string;
    area: string;
    key: string;
    version: number;
    status: string;
    definition: Record<string, unknown>;
    activatedAt: string | null;
};
type Area = { value: string; label: string };

export default function OrganisationConfigurationsIndex({
    configurations,
    areas,
}: {
    configurations: Configuration[];
    areas: Area[];
}) {
    const organisation = usePage().props.currentOrganisation!;
    return (
        <div className="space-y-6 p-4">
            <Head title="Organisation configuration" />
            <Heading
                title="Organisation configuration"
                description="Create immutable, validated versions. Preview the exact definition below, then explicitly activate it. Fixed consent, access, and service-data safeguards cannot be disabled."
            />
            {areas.length > 0 ? (
                <Card>
                    <CardHeader>
                        <CardTitle>Create configuration version</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form(organisation.slug)}
                            className="grid gap-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <label className="grid gap-1">
                                        <span>Area</span>
                                        <select
                                            name="area"
                                            className="h-9 rounded border bg-transparent px-3"
                                        >
                                            {areas.map((area) => (
                                                <option
                                                    key={area.value}
                                                    value={area.value}
                                                >
                                                    {area.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.area} />
                                    </label>
                                    <label className="grid gap-1">
                                        <span>Configuration key</span>
                                        <input
                                            name="configuration_key"
                                            defaultValue="impact"
                                            required
                                            className="h-9 rounded border bg-transparent px-3"
                                        />
                                        <InputError
                                            message={errors.configuration_key}
                                        />
                                        <small className="text-muted-foreground">
                                            Use a stable name for this
                                            definition.
                                        </small>
                                    </label>
                                    <label className="grid gap-1">
                                        <span>Validated JSON definition</span>
                                        <textarea
                                            name="definition_json"
                                            rows={10}
                                            required
                                            className="rounded border bg-transparent p-3 font-mono text-sm"
                                        />
                                        <InputError
                                            message={errors.definition_json}
                                        />
                                    </label>
                                    <Button disabled={processing}>
                                        Validate and create draft
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            ) : (
                <Card>
                    <CardContent className="pt-6 text-sm">
                        All configuration areas now have dedicated editors in
                        the workspace navigation.
                    </CardContent>
                </Card>
            )}
            <section className="space-y-3">
                <h2 className="text-xl font-semibold">
                    Version history and preview
                </h2>
                {configurations.map((configuration) => (
                    <Card key={configuration.id}>
                        <CardContent className="space-y-3 pt-6">
                            <div className="flex flex-wrap items-center gap-2">
                                <strong>
                                    {configuration.area.replaceAll('_', ' ')} ·{' '}
                                    {configuration.key} · v
                                    {configuration.version}
                                </strong>
                                <Badge>{configuration.status}</Badge>
                            </div>
                            <pre className="bg-muted overflow-auto rounded p-3 text-xs">
                                {JSON.stringify(
                                    configuration.definition,
                                    null,
                                    2,
                                )}
                            </pre>
                            {configuration.status === 'draft' ? (
                                <Form
                                    {...activate.form([
                                        organisation.slug,
                                        configuration.id,
                                    ])}
                                >
                                    <Button variant="outline">
                                        Activate this version
                                    </Button>
                                </Form>
                            ) : null}
                        </CardContent>
                    </Card>
                ))}
            </section>
        </div>
    );
}

OrganisationConfigurationsIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Organisation configuration',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
