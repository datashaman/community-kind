import { Form, Head } from '@inertiajs/react';
import { ArrowRight, FlaskConical, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { destroy } from '@/routes/demo';
import { store } from '@/routes/demo/personas';

type Persona = {
    membershipId: number;
    name: string;
    organisation: string;
    role: string;
    roleKey: string;
    responsibility: string;
    boundary: string;
    tasks: { label: string; description: string; href: string }[];
};

export default function DemoPersonas({ personas }: { personas: Persona[] }) {
    return (
        <main className="bg-background min-h-screen px-4 py-12">
            <Head title="Choose a demo persona" />
            <div className="mx-auto max-w-6xl space-y-8">
                <div className="space-y-3 text-center">
                    <FlaskConical
                        className="mx-auto size-10 text-amber-600"
                        aria-hidden="true"
                    />
                    <h1 className="font-display text-3xl font-semibold">
                        Choose a synthetic demo persona
                    </h1>
                    <p className="text-muted-foreground">
                        No personal details are collected. Demo access cannot
                        reach real Organisations or enable uploads, invitations,
                        external messaging, payments, or domains.
                    </p>
                </div>
                <div className="grid gap-5 lg:grid-cols-2">
                    {personas.map((persona) => (
                        <Card
                            key={persona.membershipId}
                            className="border-border/80 bg-card/95"
                        >
                            <CardHeader>
                                <CardTitle className="text-lg">
                                    {persona.role}
                                </CardTitle>
                                <CardDescription>
                                    {persona.organisation}
                                </CardDescription>
                                <p className="pt-2 text-sm leading-6">
                                    {persona.responsibility}
                                </p>
                            </CardHeader>
                            <CardContent className="space-y-5">
                                <div className="border-saffron bg-saffron/10 rounded-lg border-l-4 p-3 text-sm leading-6">
                                    <div className="mb-1 flex items-center gap-2 font-semibold">
                                        <ShieldCheck
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                        Permission boundary
                                    </div>
                                    {persona.boundary}
                                </div>
                                <div>
                                    <p className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                        Suggested evaluation
                                    </p>
                                    <ul className="space-y-2 text-sm">
                                        {persona.tasks.map((task) => (
                                            <li
                                                key={task.label}
                                                className="flex gap-2"
                                            >
                                                <ArrowRight
                                                    className="text-service dark:text-service-bright mt-0.5 size-4 shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <span>
                                                    <span className="font-medium">
                                                        {task.label}
                                                    </span>{' '}
                                                    <span className="text-muted-foreground">
                                                        — {task.description}
                                                    </span>
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                                <p className="text-muted-foreground text-xs">
                                    Synthetic account: {persona.name}
                                </p>
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
                                                Continue as {persona.role}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                <div className="flex justify-center border-t pt-6">
                    <Form {...destroy.form()}>
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing
                                    ? 'Preparing a clean demo…'
                                    : 'Reset with fresh demo data'}
                            </Button>
                        )}
                    </Form>
                </div>
            </div>
        </main>
    );
}
