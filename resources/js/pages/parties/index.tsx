import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show, store } from '@/routes/parties';

type Option = { value: string; label: string };
type Program = { id: number; name: string; slug: string };
type Party = {
    uuid: string;
    kind: string;
    displayName: string;
    roles: string[];
    programs: string[];
};
type Props = {
    parties: {
        data: Party[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    query: string;
    canCreate: boolean;
    programs: Program[];
    partyKinds: Option[];
    partyRoles: Option[];
};

export default function PartiesIndex({
    parties,
    query,
    canCreate,
    programs,
    partyKinds,
    partyRoles,
}: Props) {
    const organisation = usePage().props.currentOrganisation!;
    const form = useForm({
        kind: partyKinds[0]?.value ?? 'person',
        display_name: '',
        email: '',
        telephone: '',
        program_ids: [] as number[],
        roles: [] as string[],
        interests: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            interests: data.interests
                .split(',')
                .map((value) => value.trim())
                .filter(Boolean),
        }));
        form.post(store.url(organisation.slug), {
            onSuccess: () => form.reset(),
        });
    };

    return (
        <>
            <Head title="Party profiles" />
            <div className="space-y-8 p-4">
                <Heading
                    title="Party profiles"
                    description="Tenant-local people, households, and organisations."
                />
                <form
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        router.get(
                            index.url(organisation.slug),
                            {
                                query: new FormData(event.currentTarget).get(
                                    'query',
                                ),
                            },
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        name="query"
                        defaultValue={query}
                        placeholder="Search by name"
                    />
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>
                {canCreate ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Create profile</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form
                                onSubmit={submit}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                <div>
                                    <Label htmlFor="display_name">
                                        Display name
                                    </Label>
                                    <Input
                                        id="display_name"
                                        value={form.data.display_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'display_name',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={form.errors.display_name}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="kind">Party kind</Label>
                                    <select
                                        id="kind"
                                        className="h-9 w-full rounded-md border bg-transparent px-3"
                                        value={form.data.kind}
                                        onChange={(e) =>
                                            form.setData('kind', e.target.value)
                                        }
                                    >
                                        {partyKinds.map((kind) => (
                                            <option
                                                key={kind.value}
                                                value={kind.value}
                                            >
                                                {kind.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setData(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="telephone">Telephone</Label>
                                    <Input
                                        id="telephone"
                                        value={form.data.telephone}
                                        onChange={(e) =>
                                            form.setData(
                                                'telephone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <fieldset>
                                    <legend className="text-sm font-medium">
                                        Programs
                                    </legend>
                                    {programs.map((program) => (
                                        <label
                                            key={program.id}
                                            className="mt-2 flex gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={form.data.program_ids.includes(
                                                    program.id,
                                                )}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'program_ids',
                                                        e.target.checked
                                                            ? [
                                                                  ...form.data
                                                                      .program_ids,
                                                                  program.id,
                                                              ]
                                                            : form.data.program_ids.filter(
                                                                  (id) =>
                                                                      id !==
                                                                      program.id,
                                                              ),
                                                    )
                                                }
                                            />
                                            {program.name}
                                        </label>
                                    ))}
                                </fieldset>
                                <fieldset>
                                    <legend className="text-sm font-medium">
                                        Business roles
                                    </legend>
                                    {partyRoles.map((role) => (
                                        <label
                                            key={role.value}
                                            className="mt-2 flex gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={form.data.roles.includes(
                                                    role.value,
                                                )}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'roles',
                                                        e.target.checked
                                                            ? [
                                                                  ...form.data
                                                                      .roles,
                                                                  role.value,
                                                              ]
                                                            : form.data.roles.filter(
                                                                  (value) =>
                                                                      value !==
                                                                      role.value,
                                                              ),
                                                    )
                                                }
                                            />
                                            {role.label}
                                        </label>
                                    ))}
                                </fieldset>
                                <div className="md:col-span-2">
                                    <Label htmlFor="interests">
                                        Interests (comma separated)
                                    </Label>
                                    <Input
                                        id="interests"
                                        value={form.data.interests}
                                        onChange={(e) =>
                                            form.setData(
                                                'interests',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.interests}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    Create profile
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}
                <div className="space-y-3">
                    {parties.data.map((party) => (
                        <Link
                            key={party.uuid}
                            href={show.url([organisation.slug, party.uuid])}
                            className="hover:bg-muted/50 block rounded-lg border p-4"
                        >
                            <div className="flex items-center justify-between">
                                <strong>{party.displayName}</strong>
                                <Badge variant="outline">{party.kind}</Badge>
                            </div>
                            <p className="text-muted-foreground mt-2 text-sm">
                                {[...party.roles, ...party.programs].join(
                                    ' · ',
                                ) || 'No roles or programs assigned'}
                            </p>
                        </Link>
                    ))}
                </div>
                <nav className="flex flex-wrap gap-2">
                    {parties.links.map((link) => (
                        <Button
                            key={link.label}
                            variant={link.active ? 'default' : 'outline'}
                            size="sm"
                            disabled={!link.url}
                            onClick={() => link.url && router.visit(link.url)}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            </div>
        </>
    );
}

PartiesIndex.layout = (props: { currentOrganisation: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Party profiles',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
