import { Form, Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { index, update } from '@/routes/parties';
import { store as storeAddress } from '@/routes/parties/addresses';
import { store as storeConsent } from '@/routes/parties/consents';
import { store as storePortalAccessGrant } from '@/routes/parties/portal-access-grants';
import { store as storeRelationship } from '@/routes/parties/relationships';
import { store as storeSafeContact } from '@/routes/parties/safe-contact-instructions';

type Program = { id: number; name: string };
type Option = { value: string; label: string };
type Props = {
    party: any;
    canUpdate: boolean;
    canRecordConsent: boolean;
    canManageSafeContact: boolean;
    canManagePortalAccess: boolean;
    portalAccessUrl: string | null;
    programs: Program[];
    partyKinds: Option[];
    partyRoles: Option[];
    relationshipCandidates: { id: number; uuid: string; displayName: string }[];
};
const nowLocal = () =>
    new Date(Date.now() - new Date().getTimezoneOffset() * 60000)
        .toISOString()
        .slice(0, 16);
const formArray = (value: unknown): string[] =>
    Array.isArray(value)
        ? value.map(String)
        : typeof value === 'string' && value !== ''
          ? [value]
          : [];
const commaArray = (value: unknown): string[] =>
    typeof value === 'string'
        ? value
              .split(',')
              .map((item) => item.trim())
              .filter(Boolean)
        : formArray(value);

export default function PartyShow({
    party,
    canUpdate,
    canRecordConsent,
    canManageSafeContact,
    canManagePortalAccess,
    portalAccessUrl,
    programs,
    partyKinds,
    partyRoles,
    relationshipCandidates,
}: Props) {
    const organisation = usePage().props.currentOrganisation!;
    const args = [organisation.slug, party.uuid] as [string, string];
    return (
        <>
            <Head title={party.displayName} />
            <div className="space-y-8 p-4">
                <div>
                    <Link
                        className="text-muted-foreground text-sm"
                        href={index.url(organisation.slug)}
                    >
                        ← Party profiles
                    </Link>
                    <Heading
                        title={party.displayName}
                        description={`${party.kind} profile`}
                    />
                </div>
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Profile summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex flex-wrap gap-2">
                                {party.roles.map((role: string) => (
                                    <Badge key={role}>{role}</Badge>
                                ))}
                                {party.interests.map((interest: string) => (
                                    <Badge key={interest} variant="outline">
                                        {interest}
                                    </Badge>
                                ))}
                            </div>
                            <p>
                                {programs
                                    .filter((program) =>
                                        party.programIds.includes(program.id),
                                    )
                                    .map((program) => program.name)
                                    .join(' · ') || 'No programs assigned'}
                            </p>
                            {party.email ? <p>{party.email}</p> : null}
                            {party.telephone ? <p>{party.telephone}</p> : null}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Addresses and relationships</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            {party.addresses.map((address: any) => (
                                <div key={address.id}>
                                    <strong>{address.label}</strong>
                                    <p>{address.address}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {[
                                            address.serviceArea,
                                            address.countryCode,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                    </p>
                                </div>
                            ))}
                            {party.relationships.map((relationship: any) => (
                                <div key={relationship.id}>
                                    <strong>
                                        {relationship.relatedParty.displayName}
                                    </strong>{' '}
                                    <span className="text-muted-foreground">
                                        {relationship.type.replaceAll('_', ' ')}
                                    </span>
                                </div>
                            ))}
                            {party.addresses.length === 0 &&
                            party.relationships.length === 0 ? (
                                <p className="text-muted-foreground">
                                    No addresses or relationships recorded.
                                </p>
                            ) : null}
                        </CardContent>
                    </Card>
                </div>
                {canUpdate ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Profile</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...update.form(args)}
                                transform={(data) => ({
                                    ...data,
                                    program_ids: formArray(data.program_ids),
                                    roles: formArray(data.roles),
                                    interests: commaArray(data.interests),
                                })}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div>
                                            <Label htmlFor="display_name">
                                                Display name
                                            </Label>
                                            <Input
                                                id="display_name"
                                                name="display_name"
                                                defaultValue={party.displayName}
                                                required
                                            />
                                            <InputError
                                                message={errors.display_name}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="kind">Kind</Label>
                                            <select
                                                id="kind"
                                                name="kind"
                                                defaultValue={party.kind}
                                                className="h-9 w-full rounded-md border bg-transparent px-3"
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
                                                name="email"
                                                type="email"
                                                defaultValue={party.email ?? ''}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="telephone">
                                                Telephone
                                            </Label>
                                            <Input
                                                id="telephone"
                                                name="telephone"
                                                defaultValue={
                                                    party.telephone ?? ''
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
                                                        name="program_ids[]"
                                                        value={program.id}
                                                        defaultChecked={party.programIds.includes(
                                                            program.id,
                                                        )}
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
                                                        name="roles[]"
                                                        value={role.value}
                                                        defaultChecked={party.roles.includes(
                                                            role.value,
                                                        )}
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
                                                name="interests"
                                                defaultValue={party.interests.join(
                                                    ', ',
                                                )}
                                            />
                                            <InputError
                                                message={errors.interests}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save profile
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}
                {canManagePortalAccess ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Supporter portal access</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-muted-foreground text-sm">
                                Create a short-lived, single-use link for a
                                verified user. Creating another link replaces
                                the previous grant for this person and user.
                            </p>
                            <Form
                                {...storePortalAccessGrant.form(args)}
                                className="flex flex-col gap-3 sm:flex-row"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div className="flex-1">
                                            <Label htmlFor="portal-user-email">
                                                Verified user email
                                            </Label>
                                            <Input
                                                id="portal-user-email"
                                                name="email"
                                                type="email"
                                                autoComplete="off"
                                                required
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <Button
                                            className="self-end"
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Create portal link
                                        </Button>
                                    </>
                                )}
                            </Form>
                            {portalAccessUrl ? (
                                <div>
                                    <Label htmlFor="portal-access-url">
                                        New portal link
                                    </Label>
                                    <Input
                                        id="portal-access-url"
                                        value={portalAccessUrl}
                                        readOnly
                                        onFocus={(event) =>
                                            event.currentTarget.select()
                                        }
                                    />
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        Share this link securely. It is shown
                                        only once and expires shortly.
                                    </p>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Consent history</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {party.consents.map((consent: any) => (
                                <div
                                    key={consent.id}
                                    className="rounded border p-3"
                                >
                                    <div className="flex gap-2">
                                        <Badge>{consent.purpose}</Badge>
                                        <Badge variant="outline">
                                            {consent.channel}
                                        </Badge>
                                        <Badge variant="outline">
                                            {consent.decision}
                                        </Badge>
                                    </div>
                                    <p className="mt-2 text-sm">
                                        {consent.wording}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {consent.wordingVersion} ·{' '}
                                        {consent.source} ·{' '}
                                        {new Date(
                                            consent.occurredAt,
                                        ).toLocaleString()}
                                    </p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle>Timeline</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y">
                                {party.timeline.map((event: any) => (
                                    <li
                                        key={event.id}
                                        className="py-3 first:pt-0 last:pb-0"
                                    >
                                        <p className="text-sm font-medium">
                                            {event.summary}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            <time dateTime={event.occurredAt}>
                                                {new Date(
                                                    event.occurredAt,
                                                ).toLocaleString()}
                                            </time>
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>
                {canRecordConsent ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Record consent decision</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...storeConsent.form(args)}
                                className="grid gap-4 md:grid-cols-2"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <select
                                            name="purpose"
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            {!party.supporterSafe ? (
                                                <>
                                                    <option value="service">
                                                        Service
                                                    </option>
                                                    <option value="referral">
                                                        Referral
                                                    </option>
                                                    <option value="safe_contact">
                                                        Safe contact
                                                    </option>
                                                </>
                                            ) : null}
                                            <option value="supporter_updates">
                                                Supporter updates
                                            </option>
                                        </select>
                                        <select
                                            name="channel"
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            <option value="not_applicable">
                                                Not applicable
                                            </option>
                                            <option value="email">Email</option>
                                            <option value="sms">SMS</option>
                                            <option value="telephone">
                                                Telephone
                                            </option>
                                        </select>
                                        <select
                                            name="decision"
                                            className="h-9 rounded-md border bg-transparent px-3"
                                        >
                                            <option value="granted">
                                                Granted
                                            </option>
                                            <option value="withdrawn">
                                                Withdrawn
                                            </option>
                                            <option value="suppressed">
                                                Suppressed
                                            </option>
                                        </select>
                                        <Input
                                            name="wording_version"
                                            placeholder="Wording version"
                                            required
                                        />
                                        <Input
                                            name="source"
                                            placeholder="Source"
                                            required
                                        />
                                        <Textarea
                                            name="wording"
                                            placeholder="Exact consent wording"
                                            required
                                        />
                                        <Input
                                            name="occurred_at"
                                            type="datetime-local"
                                            defaultValue={nowLocal()}
                                            required
                                        />
                                        <InputError message={errors.decision} />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Record immutable decision
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}
                {canUpdate ? (
                    <div className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Add address</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...storeAddress.form(args)}
                                    className="space-y-3"
                                >
                                    {({ processing }) => (
                                        <>
                                            <Input
                                                name="label"
                                                placeholder="Label"
                                                required
                                            />
                                            <Textarea
                                                name="address"
                                                placeholder="Address"
                                                required
                                            />
                                            <Input
                                                name="service_area"
                                                placeholder="Service area"
                                            />
                                            <Input
                                                name="country_code"
                                                placeholder="Country code"
                                                maxLength={2}
                                                required
                                            />
                                            <Button disabled={processing}>
                                                Add address
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader>
                                <CardTitle>Add relationship</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    {...storeRelationship.form(args)}
                                    className="space-y-3"
                                >
                                    {({ processing }) => (
                                        <>
                                            <select
                                                name="related_party_id"
                                                className="h-9 w-full rounded-md border bg-transparent px-3"
                                            >
                                                {relationshipCandidates.map(
                                                    (candidate) => (
                                                        <option
                                                            key={candidate.id}
                                                            value={candidate.id}
                                                        >
                                                            {
                                                                candidate.displayName
                                                            }
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <Input
                                                name="type"
                                                placeholder="relationship_type"
                                                required
                                            />
                                            <Input
                                                name="started_at"
                                                type="datetime-local"
                                            />
                                            <Button disabled={processing}>
                                                Add relationship
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    </div>
                ) : null}
                {canManageSafeContact ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Safe-contact instructions</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {party.safeContactInstructions.map(
                                (instruction: any) => (
                                    <div
                                        key={instruction.id}
                                        className="rounded border p-3 text-sm"
                                    >
                                        {instruction.instruction}
                                        <p className="text-muted-foreground text-xs">
                                            {instruction.source}
                                        </p>
                                    </div>
                                ),
                            )}
                            <Form
                                {...storeSafeContact.form(args)}
                                className="space-y-3"
                            >
                                {({ processing }) => (
                                    <>
                                        <Textarea
                                            name="instruction"
                                            placeholder="Restricted contact instructions"
                                            required
                                        />
                                        <Input
                                            name="source"
                                            placeholder="Source"
                                            required
                                        />
                                        <Input
                                            name="effective_at"
                                            type="datetime-local"
                                            defaultValue={nowLocal()}
                                            required
                                        />
                                        <Button disabled={processing}>
                                            Record instruction
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </>
    );
}

PartyShow.layout = (props: {
    currentOrganisation: { slug: string };
    party: { uuid: string; displayName: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Party profiles',
            href: index(props.currentOrganisation.slug),
        },
        { title: props.party.displayName, href: '#' },
    ],
});
