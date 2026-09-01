import { Head } from '@inertiajs/react';

type AuditEvent = {
    id: string;
    event: string;
    domain: string;
    actor: string;
    subject: string;
    occurredAt: string;
};

export default function AuditIndex({
    events,
    role,
}: {
    events: AuditEvent[];
    role: string;
}) {
    return (
        <div className="space-y-6 p-4 md:p-6">
            <Head title="Audit history" />
            <header className="space-y-1">
                <h1 className="font-display text-2xl font-semibold">
                    Audit history
                </h1>
                <p className="text-muted-foreground text-sm">
                    A policy-filtered projection for {role}. Event payloads and
                    identifiers are intentionally excluded.
                </p>
            </header>

            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">
                        Audit events permitted for your current role and scope
                    </caption>
                    <thead className="bg-muted/50">
                        <tr>
                            <th scope="col" className="px-4 py-3">
                                Time
                            </th>
                            <th scope="col" className="px-4 py-3">
                                Event
                            </th>
                            <th scope="col" className="px-4 py-3">
                                Domain
                            </th>
                            <th scope="col" className="px-4 py-3">
                                Actor
                            </th>
                            <th scope="col" className="px-4 py-3">
                                Subject type
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y">
                        {events.map((event) => (
                            <tr key={event.id}>
                                <td className="px-4 py-3 whitespace-nowrap">
                                    <time dateTime={event.occurredAt}>
                                        {new Date(
                                            event.occurredAt,
                                        ).toLocaleString()}
                                    </time>
                                </td>
                                <td className="px-4 py-3 font-medium">
                                    {event.event}
                                </td>
                                <td className="px-4 py-3 capitalize">
                                    {event.domain}
                                </td>
                                <td className="px-4 py-3">{event.actor}</td>
                                <td className="px-4 py-3">{event.subject}</td>
                            </tr>
                        ))}
                        {events.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="text-muted-foreground px-4 py-8 text-center"
                                >
                                    No audit events are visible within your
                                    current policy scope.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

AuditIndex.layout = () => ({
    breadcrumbs: [{ title: 'Audit history', href: '#' }],
});
