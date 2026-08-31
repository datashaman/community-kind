import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, show, store } from '@/routes/audience-segments';

type Segment = {
    id: string;
    name: string;
    criteria: Record<string, string | boolean | null>;
    eligibleCount: number;
};
type Option = { value: string; label: string };
type Props = {
    segments: Segment[];
    options: {
        purpose: string;
        channels: Option[];
        roles: Option[];
        serviceAreas: string[];
        interests: Array<{ slug: string; label: string }>;
        campaignSources: string[];
    };
};

export default function AudienceSegmentsIndex({ segments, options }: Props) {
    const organisation = usePage().props.currentOrganisation!;

    return (
        <>
            <Head title="Saved audiences" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Saved audiences"
                    description="Reproducible supporter-only audiences. Active safe-contact restrictions, withdrawn consent, and suppression always win over segment criteria."
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Create saved audience</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...store.form(organisation.slug)}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <label className="grid gap-1">
                                        <span>Name</span>
                                        <input
                                            name="name"
                                            required
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        />
                                        <InputError message={errors.name} />
                                    </label>
                                    <input
                                        type="hidden"
                                        name="purpose"
                                        value={options.purpose}
                                    />
                                    <Select
                                        name="channel"
                                        label="Consent channel"
                                        options={options.channels}
                                    />
                                    <Select
                                        name="role"
                                        label="Supporter role"
                                        options={options.roles}
                                    />
                                    <Select
                                        name="service_area"
                                        label="Service area (optional)"
                                        options={options.serviceAreas.map(
                                            (value) => ({
                                                value,
                                                label: value,
                                            }),
                                        )}
                                        optional
                                    />
                                    <Select
                                        name="interest"
                                        label="Interest (optional)"
                                        options={options.interests.map(
                                            (interest) => ({
                                                value: interest.slug,
                                                label: interest.label,
                                            }),
                                        )}
                                        optional
                                    />
                                    <Select
                                        name="campaign_source"
                                        label="Donation source (optional)"
                                        options={options.campaignSources.map(
                                            (value) => ({
                                                value,
                                                label: value,
                                            }),
                                        )}
                                        optional
                                    />
                                    <label className="flex items-center gap-2">
                                        <input
                                            type="hidden"
                                            name="donation_activity"
                                            value="0"
                                        />
                                        <input
                                            type="checkbox"
                                            name="donation_activity"
                                            value="1"
                                        />
                                        Require a successful simulated donation
                                    </label>
                                    <Button type="submit" disabled={processing}>
                                        Save and preview
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                <section aria-labelledby="saved-segments" className="space-y-3">
                    <h2 id="saved-segments" className="text-xl font-semibold">
                        Saved definitions
                    </h2>
                    {segments.map((segment) => (
                        <Link
                            key={segment.id}
                            href={show([organisation.slug, segment.id])}
                            className="hover:bg-muted/50 block rounded-lg border p-4"
                        >
                            <strong>{segment.name}</strong>
                            <p className="text-muted-foreground text-sm">
                                {segment.eligibleCount} currently eligible
                            </p>
                        </Link>
                    ))}
                </section>
            </div>
        </>
    );
}

function Select({
    name,
    label,
    options,
    optional = false,
}: {
    name: string;
    label: string;
    options: Option[];
    optional?: boolean;
}) {
    return (
        <label className="grid gap-1">
            <span>{label}</span>
            <select
                name={name}
                className="h-9 rounded-md border bg-transparent px-3"
                required={!optional}
            >
                {optional ? <option value="">Any</option> : null}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}

AudienceSegmentsIndex.layout = (props: {
    currentOrganisation: { slug: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Saved audiences',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
