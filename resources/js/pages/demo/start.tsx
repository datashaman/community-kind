import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, RotateCcw, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { home } from '@/routes';
import { store } from '@/routes/demo';

export default function DemoStart({
    lifetimeHours,
}: {
    lifetimeHours: number;
}) {
    return (
        <main className="bg-background text-foreground relative min-h-screen overflow-hidden">
            <Head title="Explore the CommunityKind demo" />
            <div
                className="bg-service absolute inset-x-0 top-0 h-1"
                aria-hidden="true"
            />
            <div className="mx-auto grid min-h-screen w-full max-w-6xl items-center gap-12 px-6 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:px-10">
                <section aria-labelledby="demo-heading" className="space-y-8">
                    <Link
                        href={home()}
                        className="text-foreground/80 focus-visible:outline-service inline-flex items-center gap-2 text-sm font-semibold underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4"
                    >
                        <span className="bg-service size-2 rounded-full" />
                        CommunityKind
                    </Link>
                    <div className="max-w-2xl space-y-5">
                        <p className="text-service dark:text-service-bright text-sm font-semibold tracking-[0.16em] uppercase">
                            A safe, synthetic workspace
                        </p>
                        <h1
                            id="demo-heading"
                            className="font-display text-4xl font-semibold tracking-[-0.015em] text-balance sm:text-6xl"
                        >
                            Follow the work from first contact to community
                            impact.
                        </h1>
                        <p className="text-muted-foreground max-w-xl text-lg leading-8">
                            Step into several staff perspectives across two
                            fictional human-services organisations. No account,
                            setup, or real personal information is required.
                        </p>
                    </div>
                    <div
                        className="text-muted-foreground flex max-w-xl items-center gap-3 text-sm"
                        aria-label="Demo journey"
                    >
                        {[
                            'Choose a role',
                            'Explore real workflows',
                            'Reset anytime',
                        ].map((step, index) => (
                            <div
                                key={step}
                                className="flex min-w-0 flex-1 items-center gap-3"
                            >
                                <span className="bg-service flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white">
                                    {index + 1}
                                </span>
                                <span className="leading-tight">{step}</span>
                                {index < 2 && (
                                    <span
                                        className="bg-service/30 hidden h-px flex-1 sm:block"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                <section
                    aria-label="Prepare demo"
                    className="border-border/80 bg-card/90 rounded-2xl border p-8 shadow-[0_18px_50px_rgba(23,59,87,0.10)] backdrop-blur"
                >
                    <div className="space-y-7">
                        <div className="space-y-3">
                            <div className="bg-saffron/20 text-civic dark:text-saffron flex size-11 items-center justify-center rounded-xl">
                                <ShieldCheck
                                    className="size-6"
                                    aria-hidden="true"
                                />
                            </div>
                            <h2 className="font-display text-2xl font-semibold">
                                Your isolated evaluation space
                            </h2>
                            <p className="text-muted-foreground leading-7">
                                The workspace lasts up to {lifetimeHours} hours.
                                Uploads, invitations, outbound messages,
                                payments, and custom domains stay disabled.
                            </p>
                        </div>

                        <ul className="text-foreground/85 space-y-3 text-sm">
                            <li className="flex gap-3">
                                <span className="bg-service mt-2 size-1.5 shrink-0 rounded-full" />
                                Fictional people, cases, donations, volunteers,
                                and outcomes
                            </li>
                            <li className="flex gap-3">
                                <span className="bg-leaf mt-2 size-1.5 shrink-0 rounded-full" />
                                Role-specific permissions and realistic safety
                                boundaries
                            </li>
                            <li className="flex gap-3">
                                <span className="bg-coral mt-2 size-1.5 shrink-0 rounded-full" />
                                A clean reset whenever you want to start again
                            </li>
                        </ul>

                        <Form {...store.form()} className="space-y-3">
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="bg-service hover:bg-civic focus-visible:ring-service w-full text-white"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Preparing your workspace…'
                                            : 'Prepare my demo'}
                                        {!processing && (
                                            <ArrowRight
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                    </Button>
                                    <InputError message={errors.demo} />
                                </>
                            )}
                        </Form>

                        <p className="text-muted-foreground flex items-center justify-center gap-2 text-center text-xs">
                            <RotateCcw
                                className="size-3.5"
                                aria-hidden="true"
                            />
                            Repeat submissions resume the same pending
                            workspace.
                        </p>
                    </div>
                </section>
            </div>
        </main>
    );
}
