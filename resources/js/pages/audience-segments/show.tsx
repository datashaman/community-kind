import { Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { index, show } from '@/routes/audience-segments';

type Member = {
    uuid: string;
    displayName: string;
    role: string;
    serviceAreas: string[];
    interests: string[];
    donationCount: number;
    consentedAt: string;
};

export default function AudienceSegmentShow({
    segment,
    audience,
    eligibleCount,
}: {
    segment: {
        id: string;
        name: string;
        criteria: Record<string, string | boolean | null>;
    };
    audience: Member[];
    eligibleCount: number;
}) {
    return (
        <>
            <Head title={segment.name} />
            <div className="space-y-6 p-4">
                <Heading
                    title={segment.name}
                    description={`${eligibleCount} currently eligible supporter(s). This preview contains no service-case or client-role fields.`}
                />
                <Card>
                    <CardHeader>
                        <CardTitle>Saved criteria</CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-wrap gap-2">
                        {Object.entries(segment.criteria).map(([key, value]) =>
                            value !== null ? (
                                <Badge key={key} variant="outline">
                                    {key.replaceAll('_', ' ')}: {String(value)}
                                </Badge>
                            ) : null,
                        )}
                    </CardContent>
                </Card>
                <section
                    aria-labelledby="eligible-supporters"
                    className="space-y-3"
                >
                    <h2
                        id="eligible-supporters"
                        className="text-xl font-semibold"
                    >
                        Eligible supporters
                    </h2>
                    {audience.length === 0 ? (
                        <p className="text-muted-foreground">
                            No supporters currently meet every safety and
                            consent rule.
                        </p>
                    ) : (
                        audience.map((member) => (
                            <Card key={member.uuid}>
                                <CardContent className="grid gap-2 p-4 sm:grid-cols-2">
                                    <div>
                                        <strong>{member.displayName}</strong>
                                        <p className="text-muted-foreground text-sm">
                                            {member.role.replaceAll('_', ' ')}
                                        </p>
                                    </div>
                                    <div className="text-sm">
                                        <p>
                                            {member.donationCount} matching
                                            donation(s)
                                        </p>
                                        <p>
                                            Consent:{' '}
                                            {new Date(
                                                member.consentedAt,
                                            ).toLocaleString()}
                                        </p>
                                    </div>
                                    {member.serviceAreas.length > 0 ? (
                                        <p className="text-sm">
                                            Areas:{' '}
                                            {member.serviceAreas.join(', ')}
                                        </p>
                                    ) : null}
                                    {member.interests.length > 0 ? (
                                        <p className="text-sm">
                                            Interests:{' '}
                                            {member.interests.join(', ')}
                                        </p>
                                    ) : null}
                                </CardContent>
                            </Card>
                        ))
                    )}
                </section>
            </div>
        </>
    );
}

AudienceSegmentShow.layout = (props: {
    currentOrganisation: { slug: string };
    segment: { id: string; name: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Saved audiences',
            href: index(props.currentOrganisation.slug),
        },
        {
            title: props.segment.name,
            href: show([props.currentOrganisation.slug, props.segment.id]),
        },
    ],
});
