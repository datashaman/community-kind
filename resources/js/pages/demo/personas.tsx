import { Form, Head } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { store } from '@/routes/demo/personas';

type Persona = {
    membershipId: number;
    name: string;
    organisation: string;
    role: string;
};

export default function DemoPersonas({ personas }: { personas: Persona[] }) {
    return (
        <main className="bg-muted/30 min-h-screen px-4 py-12">
            <Head title="Choose a demo persona" />
            <div className="mx-auto max-w-5xl space-y-8">
                <div className="space-y-3 text-center">
                    <FlaskConical
                        className="mx-auto size-10 text-amber-600"
                        aria-hidden="true"
                    />
                    <h1 className="text-3xl font-semibold">
                        Choose a synthetic demo persona
                    </h1>
                    <p className="text-muted-foreground">
                        No personal details are collected. Demo access cannot
                        reach real Organisations or enable uploads, invitations,
                        external messaging, payments, or domains.
                    </p>
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {personas.map((persona) => (
                        <Card key={persona.membershipId}>
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    {persona.role}
                                </CardTitle>
                                <CardDescription>
                                    {persona.organisation}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <p className="text-sm">{persona.name}</p>
                                <Form {...store.form()}>
                                    {({ processing }) => (
                                        <>
                                            <input
                                                type="hidden"
                                                name="membership_id"
                                                value={persona.membershipId}
                                            />
                                            <Button
                                                className="w-full"
                                                type="submit"
                                                disabled={processing}
                                            >
                                                Continue as this persona
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        </main>
    );
}
