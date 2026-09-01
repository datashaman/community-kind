import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, show, store } from '@/routes/intakes';

type FieldDefinition = {
    key: string;
    label: string;
    fieldType: 'text' | 'textarea' | 'boolean' | 'date';
    required: boolean;
};
type Program = {
    id: number;
    name: string;
    intakeFields: FieldDefinition[];
    riskFlags: { key: string; label: string }[];
};
type Party = { uuid: string; displayName: string };
type Intake = {
    id: string;
    party: Party;
    program: { id: number; name: string };
    status: string;
    urgency: string;
};
type Props = {
    intakes: {
        data: Intake[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    programs: Program[];
    parties: Party[];
};

export default function IntakesIndex({ intakes, programs, parties }: Props) {
    const organisation = usePage().props.currentOrganisation!;
    const form = useForm({
        program_id: programs[0]?.id ?? 0,
        party_uuid: parties[0]?.uuid ?? '',
        source: 'staff_referral',
        narrative: '',
        presenting_needs: '',
        email: '',
        telephone: '',
        intake_fields: {} as Record<string, string | boolean>,
        risk_flags: [] as string[],
        consent_granted: false,
        consent_source: 'verbal',
        idempotency_key: crypto.randomUUID(),
    });
    const program = programs.find(
        (candidate) => candidate.id === Number(form.data.program_id),
    );
    const fields = program?.intakeFields ?? [];
    const risks = program?.riskFlags ?? [];
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(organisation.slug));
    };

    return (
        <>
            <Head title="Service requests" />
            <div className="space-y-8 p-4">
                <Heading
                    title="Service requests"
                    description="Record referrals, review likely duplicates, triage requests, and assign accepted cases."
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Record a request</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={submit}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            <div>
                                <Label htmlFor="program_id">Program</Label>
                                <select
                                    id="program_id"
                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                    value={form.data.program_id}
                                    onChange={(event) => {
                                        form.setData(
                                            'program_id',
                                            Number(event.target.value),
                                        );
                                        form.setData('intake_fields', {});
                                        form.setData('risk_flags', []);
                                    }}
                                >
                                    {programs.map((option) => (
                                        <option
                                            key={option.id}
                                            value={option.id}
                                        >
                                            {option.name}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.program_id} />
                            </div>
                            <div>
                                <Label htmlFor="party_uuid">Party</Label>
                                <select
                                    id="party_uuid"
                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                    value={form.data.party_uuid}
                                    onChange={(event) =>
                                        form.setData(
                                            'party_uuid',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {parties.map((party) => (
                                        <option
                                            key={party.uuid}
                                            value={party.uuid}
                                        >
                                            {party.displayName}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={form.errors.party_uuid} />
                            </div>
                            <div>
                                <Label htmlFor="source">Referral source</Label>
                                <select
                                    id="source"
                                    className="h-9 w-full rounded-md border bg-transparent px-3"
                                    value={form.data.source}
                                    onChange={(event) =>
                                        form.setData(
                                            'source',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="staff_referral">
                                        Staff referral
                                    </option>
                                    <option value="self_referral">
                                        Self-referral
                                    </option>
                                    <option value="partner_referral">
                                        Partner referral
                                    </option>
                                    <option value="phone">Phone</option>
                                    <option value="walk_in">Walk-in</option>
                                    <option value="online">Online</option>
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label htmlFor="email">
                                        Submitted email
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="telephone">
                                        Submitted telephone
                                    </Label>
                                    <Input
                                        id="telephone"
                                        value={form.data.telephone}
                                        onChange={(event) =>
                                            form.setData(
                                                'telephone',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="presenting_needs">
                                    Presenting needs
                                </Label>
                                <Textarea
                                    id="presenting_needs"
                                    value={form.data.presenting_needs}
                                    onChange={(event) =>
                                        form.setData(
                                            'presenting_needs',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={form.errors.presenting_needs}
                                />
                            </div>
                            <div className="md:col-span-2">
                                <Label htmlFor="narrative">
                                    Intake narrative
                                </Label>
                                <Textarea
                                    id="narrative"
                                    value={form.data.narrative}
                                    onChange={(event) =>
                                        form.setData(
                                            'narrative',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <InputError message={form.errors.narrative} />
                            </div>
                            {fields.map((field) => (
                                <div
                                    key={field.key}
                                    className={
                                        field.fieldType === 'textarea'
                                            ? 'md:col-span-2'
                                            : ''
                                    }
                                >
                                    <Label htmlFor={`field-${field.key}`}>
                                        {field.label}
                                    </Label>
                                    {field.fieldType === 'textarea' ? (
                                        <Textarea
                                            id={`field-${field.key}`}
                                            value={String(
                                                form.data.intake_fields[
                                                    field.key
                                                ] ?? '',
                                            )}
                                            onChange={(event) =>
                                                form.setData('intake_fields', {
                                                    ...form.data.intake_fields,
                                                    [field.key]:
                                                        event.target.value,
                                                })
                                            }
                                            required={field.required}
                                        />
                                    ) : field.fieldType === 'boolean' ? (
                                        <input
                                            id={`field-${field.key}`}
                                            className="ml-3 size-4"
                                            type="checkbox"
                                            required={field.required}
                                            checked={Boolean(
                                                form.data.intake_fields[
                                                    field.key
                                                ],
                                            )}
                                            onChange={(event) =>
                                                form.setData('intake_fields', {
                                                    ...form.data.intake_fields,
                                                    [field.key]:
                                                        event.target.checked,
                                                })
                                            }
                                        />
                                    ) : (
                                        <Input
                                            id={`field-${field.key}`}
                                            type={field.fieldType}
                                            value={String(
                                                form.data.intake_fields[
                                                    field.key
                                                ] ?? '',
                                            )}
                                            onChange={(event) =>
                                                form.setData('intake_fields', {
                                                    ...form.data.intake_fields,
                                                    [field.key]:
                                                        event.target.value,
                                                })
                                            }
                                            required={field.required}
                                        />
                                    )}
                                    <InputError
                                        message={
                                            form.errors[
                                                `intake_fields.${field.key}`
                                            ]
                                        }
                                    />
                                </div>
                            ))}
                            {risks.length > 0 ? (
                                <fieldset className="md:col-span-2">
                                    <legend className="text-sm font-medium">
                                        Risk indicators reported at intake
                                    </legend>
                                    <div className="mt-2 flex flex-wrap gap-4">
                                        {risks.map((risk) => (
                                            <label
                                                key={risk.key}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.risk_flags.includes(
                                                        risk.key,
                                                    )}
                                                    onChange={(event) =>
                                                        form.setData(
                                                            'risk_flags',
                                                            event.target.checked
                                                                ? [
                                                                      ...form
                                                                          .data
                                                                          .risk_flags,
                                                                      risk.key,
                                                                  ]
                                                                : form.data.risk_flags.filter(
                                                                      (key) =>
                                                                          key !==
                                                                          risk.key,
                                                                  ),
                                                        )
                                                    }
                                                />
                                                {risk.label}
                                            </label>
                                        ))}
                                    </div>
                                </fieldset>
                            ) : null}
                            <label className="flex items-center gap-2 text-sm md:col-span-2">
                                <input
                                    type="checkbox"
                                    checked={form.data.consent_granted}
                                    onChange={(event) =>
                                        form.setData(
                                            'consent_granted',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Service consent was granted using wording
                                version service-intake-v1
                            </label>
                            <InputError message={form.errors.consent_granted} />
                            <div className="md:col-span-2">
                                <Button
                                    disabled={
                                        form.processing ||
                                        programs.length === 0 ||
                                        parties.length === 0
                                    }
                                >
                                    Record draft request
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
                <div className="space-y-3">
                    {intakes.data.map((intake) => (
                        <Link
                            key={intake.id}
                            href={show.url([organisation.slug, intake.id])}
                            className="hover:bg-muted/40 block rounded-lg border p-4"
                        >
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="font-medium">
                                        {intake.party.displayName}
                                    </p>
                                    <p className="text-muted-foreground text-sm">
                                        {intake.program.name}
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Badge variant="outline">
                                        {intake.urgency.replace('_', ' ')}
                                    </Badge>
                                    <Badge>
                                        {intake.status.replace('_', ' ')}
                                    </Badge>
                                </div>
                            </div>
                        </Link>
                    ))}
                    {intakes.data.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            No service requests are visible in your Programs
                            yet.
                        </p>
                    ) : null}
                </div>
            </div>
        </>
    );
}

IntakesIndex.layout = (props: { currentOrganisation: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Service requests',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
