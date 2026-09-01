import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy as revokeAccess } from '@/routes/portal/access';
import { update as updatePreferences } from '@/routes/portal/consent-preferences';
import { update as updateProfile } from '@/routes/portal/profile';
import { destroy as cancelMandate } from '@/routes/portal/recurring-mandates';
import { destroy as cancelRegistration } from '@/routes/portal/registrations';

type Profile = {
    displayName: string;
    email: string | null;
    telephone: string | null;
};
type RecurringMandate = {
    id: string;
    amountMinor: number;
    currency: string;
    interval: string;
    status: string;
    canCancel: boolean;
};
type Registration = {
    id: string;
    kind: string;
    title: string;
    status: string;
    startsAt: string | null;
    canCancel: boolean;
    volunteer: {
        applicationStatus: string;
        onboardingStatus: string;
        credentials: {
            type: string;
            status: string;
            expiresAt: string | null;
        }[];
        assignments: {
            title: string;
            startsAt: string;
            status: string;
            minutes: number | null;
        }[];
    } | null;
    event: {
        status: string;
        startsAt: string;
        remindedAt: string | null;
    } | null;
};
type Props = {
    organisation: { name: string; slug: string };
    profile: Profile;
    preferences: Record<'email' | 'sms' | 'telephone', boolean>;
    recurringMandates: RecurringMandate[];
    registrations: Registration[];
    inKindOffers: {
        id: string;
        category: string;
        quantity: string;
        unit: string;
        condition: string;
        status: string;
        outcome: string | null;
    }[];
};

const formArray = (value: unknown): string[] =>
    Array.isArray(value)
        ? value.map(String)
        : typeof value === 'string' && value !== ''
          ? [value]
          : [];

export default function PortalShow({
    organisation,
    profile,
    preferences,
    recurringMandates,
    registrations,
    inKindOffers,
}: Props) {
    const slug = organisation.slug;

    return (
        <>
            <Head title={`My supporter profile · ${organisation.name}`} />
            <div className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
                <header className="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div className="mx-auto max-w-5xl px-4 py-6 sm:px-6">
                        <p className="text-sm font-medium text-emerald-700 dark:text-emerald-400">
                            {organisation.name}
                        </p>
                        <h1 className="font-display mt-1 text-3xl font-semibold tracking-[-0.015em]">
                            My supporter profile
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-slate-600 dark:text-slate-300">
                            Manage your contact details, communication choices,
                            recurring gifts, and registrations.
                        </p>
                    </div>
                </header>

                <main className="mx-auto grid max-w-5xl gap-6 px-4 py-8 sm:px-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...updateProfile.form(slug)}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <div>
                                            <Label htmlFor="display_name">
                                                Name
                                            </Label>
                                            <Input
                                                id="display_name"
                                                name="display_name"
                                                defaultValue={
                                                    profile.displayName
                                                }
                                                autoComplete="name"
                                                required
                                            />
                                            <InputError
                                                message={errors.display_name}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="email">Email</Label>
                                            <Input
                                                id="email"
                                                name="email"
                                                type="email"
                                                defaultValue={
                                                    profile.email ?? ''
                                                }
                                                autoComplete="email"
                                            />
                                            <InputError
                                                message={errors.email}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="telephone">
                                                Telephone
                                            </Label>
                                            <Input
                                                id="telephone"
                                                name="telephone"
                                                type="tel"
                                                defaultValue={
                                                    profile.telephone ?? ''
                                                }
                                                autoComplete="tel"
                                            />
                                            <InputError
                                                message={errors.telephone}
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save contact details
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Communication preferences</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                {...updatePreferences.form(slug)}
                                transform={(data) => ({
                                    ...data,
                                    channels: formArray(data.channels),
                                })}
                                className="space-y-4"
                            >
                                {({ errors, processing }) => (
                                    <>
                                        <fieldset>
                                            <legend className="text-sm text-slate-600 dark:text-slate-300">
                                                Send me supporter updates by:
                                            </legend>
                                            {[
                                                ['email', 'Email'],
                                                ['sms', 'SMS'],
                                                ['telephone', 'Telephone'],
                                            ].map(([value, label]) => (
                                                <label
                                                    key={value}
                                                    className="mt-3 flex items-center gap-3 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-700"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="channels[]"
                                                        value={value}
                                                        defaultChecked={
                                                            preferences[
                                                                value as keyof typeof preferences
                                                            ]
                                                        }
                                                        className="size-4 accent-emerald-600"
                                                    />
                                                    {label}
                                                </label>
                                            ))}
                                        </fieldset>
                                        <InputError
                                            message={
                                                errors.channels ??
                                                errors['channels.0']
                                            }
                                        />
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Save communication choices
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Recurring gifts</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recurringMandates.length === 0 ? (
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    You have no recurring gifts with this
                                    organisation.
                                </p>
                            ) : null}
                            {recurringMandates.map((mandate) => (
                                <div
                                    key={mandate.id}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {new Intl.NumberFormat(undefined, {
                                                style: 'currency',
                                                currency: mandate.currency,
                                            }).format(
                                                mandate.amountMinor / 100,
                                            )}{' '}
                                            / {mandate.interval}
                                        </p>
                                        <Badge variant="outline">
                                            {mandate.status}
                                        </Badge>
                                    </div>
                                    {mandate.canCancel ? (
                                        <Form
                                            {...cancelMandate.form([
                                                slug,
                                                mandate.id,
                                            ])}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    disabled={processing}
                                                >
                                                    Cancel recurring gift
                                                </Button>
                                            )}
                                        </Form>
                                    ) : null}
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Registrations</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {registrations.length === 0 ? (
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    You have no volunteer or event
                                    registrations.
                                </p>
                            ) : null}
                            {registrations.map((registration) => (
                                <div
                                    key={registration.id}
                                    className="rounded-md border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                        <div>
                                            <p className="text-xs font-medium tracking-wide text-emerald-700 uppercase dark:text-emerald-400">
                                                {registration.kind}
                                            </p>
                                            <p className="font-medium">
                                                {registration.title}
                                            </p>
                                            {registration.startsAt ? (
                                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                                    {new Date(
                                                        registration.startsAt,
                                                    ).toLocaleString()}
                                                </p>
                                            ) : null}
                                            <Badge variant="outline">
                                                {registration.status}
                                            </Badge>
                                            {registration.volunteer ? (
                                                <div className="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-300">
                                                    <p>
                                                        Application:{' '}
                                                        {
                                                            registration
                                                                .volunteer
                                                                .applicationStatus
                                                        }{' '}
                                                        · Onboarding:{' '}
                                                        {
                                                            registration
                                                                .volunteer
                                                                .onboardingStatus
                                                        }
                                                    </p>
                                                    {registration.volunteer.credentials.map(
                                                        (credential) => (
                                                            <p
                                                                key={`${credential.type}-${credential.expiresAt ?? 'none'}`}
                                                            >
                                                                {
                                                                    credential.type
                                                                }
                                                                :{' '}
                                                                {
                                                                    credential.status
                                                                }
                                                            </p>
                                                        ),
                                                    )}
                                                    {registration.volunteer.assignments.map(
                                                        (assignment) => (
                                                            <p
                                                                key={`${assignment.title}-${assignment.startsAt}`}
                                                            >
                                                                {
                                                                    assignment.title
                                                                }{' '}
                                                                ·{' '}
                                                                {new Date(
                                                                    assignment.startsAt,
                                                                ).toLocaleString()}{' '}
                                                                ·{' '}
                                                                {
                                                                    assignment.status
                                                                }
                                                                {assignment.minutes
                                                                    ? ` · ${assignment.minutes} minutes`
                                                                    : ''}
                                                            </p>
                                                        ),
                                                    )}
                                                </div>
                                            ) : null}
                                            {registration.event ? (
                                                <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                                                    Event status:{' '}
                                                    {registration.event.status}{' '}
                                                    ·{' '}
                                                    {new Date(
                                                        registration.event
                                                            .startsAt,
                                                    ).toLocaleString()}
                                                    {registration.event
                                                        .remindedAt
                                                        ? ' · reminder recorded'
                                                        : ''}
                                                </p>
                                            ) : null}
                                        </div>
                                        {registration.canCancel ? (
                                            <Form
                                                {...cancelRegistration.form([
                                                    slug,
                                                    registration.id,
                                                ])}
                                            >
                                                {({ processing }) => (
                                                    <Button
                                                        type="submit"
                                                        variant="outline"
                                                        disabled={processing}
                                                    >
                                                        Cancel registration
                                                    </Button>
                                                )}
                                            </Form>
                                        ) : null}
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>In-kind offers</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {inKindOffers.length === 0 ? (
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    You have no in-kind offers with this
                                    organisation.
                                </p>
                            ) : null}
                            {inKindOffers.map((offer) => (
                                <div
                                    key={offer.id}
                                    className="rounded-md border border-slate-200 p-4 dark:border-slate-700"
                                >
                                    <p className="font-medium">
                                        {offer.category}: {offer.quantity}{' '}
                                        {offer.unit}
                                    </p>
                                    <p className="text-sm">
                                        {offer.condition} · {offer.status}
                                    </p>
                                    {offer.outcome ? (
                                        <p className="text-sm">
                                            Outcome: {offer.outcome}
                                        </p>
                                    ) : null}
                                </div>
                            ))}
                        </CardContent>
                    </Card>

                    <section
                        aria-labelledby="portal-access-heading"
                        className="rounded-lg border border-red-200 bg-red-50 p-5 lg:col-span-2 dark:border-red-900 dark:bg-red-950/30"
                    >
                        <h2
                            id="portal-access-heading"
                            className="font-semibold"
                        >
                            Portal access
                        </h2>
                        <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            Revoking access signs you out and permanently
                            invalidates this portal grant. Staff can issue a new
                            link later.
                        </p>
                        <Form {...revokeAccess.form(slug)} className="mt-4">
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    Revoke my portal access
                                </Button>
                            )}
                        </Form>
                    </section>
                </main>
            </div>
        </>
    );
}
