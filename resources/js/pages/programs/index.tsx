import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index } from '@/routes/programs';
import { update } from '@/routes/organisations/programs';

type Program = {
    id: number;
    name: string;
    slug: string;
    configuration: Record<string, unknown>;
    canUpdate: boolean;
};

function ProgramEditor({
    program,
    organisation,
}: {
    program: Program;
    organisation: string;
}) {
    const [name, setName] = useState(program.name);
    const [slug, setSlug] = useState(program.slug);
    const [configuration, setConfiguration] = useState(
        JSON.stringify(program.configuration ?? {}, null, 2),
    );
    const [error, setError] = useState('');
    const save = () => {
        try {
            const parsed = JSON.parse(configuration);
            setError('');
            router.patch(update.url([organisation, program.slug]), {
                name,
                slug,
                configuration: parsed,
            });
        } catch {
            setError('Configuration must be valid JSON.');
        }
    };
    return (
        <Card>
            <CardHeader>
                <CardTitle>{program.name}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div>
                    <Label>Name</Label>
                    <Input
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                        disabled={!program.canUpdate}
                    />
                </div>
                <div>
                    <Label>Slug</Label>
                    <Input
                        value={slug}
                        onChange={(event) => setSlug(event.target.value)}
                        disabled={!program.canUpdate}
                    />
                </div>
                <div>
                    <Label>
                        Labels, stages, outcome measures and taxonomies
                    </Label>
                    <Textarea
                        className="min-h-72 font-mono text-xs"
                        value={configuration}
                        onChange={(event) =>
                            setConfiguration(event.target.value)
                        }
                        disabled={!program.canUpdate}
                    />
                    <p className="text-muted-foreground mt-1 text-xs">
                        Structured JSON is validated before it is saved.
                    </p>
                    {error ? (
                        <p className="text-destructive text-sm">{error}</p>
                    ) : null}
                </div>
                {program.canUpdate ? (
                    <Button onClick={save}>Save configuration</Button>
                ) : null}
            </CardContent>
        </Card>
    );
}

export default function ProgramsIndex({ programs }: { programs: Program[] }) {
    const organisation = usePage().props.currentOrganisation!;
    return (
        <>
            <Head title="Program configuration" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Program configuration"
                    description="Change operational language, stages, measures, and taxonomies without a deployment."
                />
                <div className="grid gap-6 xl:grid-cols-2">
                    {programs.map((program) => (
                        <ProgramEditor
                            key={program.id}
                            program={program}
                            organisation={organisation.slug}
                        />
                    ))}
                </div>
            </div>
        </>
    );
}
ProgramsIndex.layout = (props: { currentOrganisation: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Program configuration',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
