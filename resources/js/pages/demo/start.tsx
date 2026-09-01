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
        <main className="relative min-h-screen overflow-hidden bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
            <Head title="Explore the CommunityKind demo" />
            <div
                className="absolute inset-x-0 top-0 h-1 bg-teal-700"
                aria-hidden="true"
            />
            <div className="mx-auto grid min-h-screen w-full max-w-6xl items-center gap-12 px-6 py-16 lg:grid-cols-[1.1fr_0.9fr] lg:px-10">
                <section aria-labelledby="demo-heading" className="space-y-8">
                    <Link
                        href={home()}
                        className="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 underline-offset-4 hover:underline focus-visible:rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-700 dark:text-slate-200"
                    >
                        <span className="size-2 rounded-full bg-teal-700" />
                        CommunityKind
                    </Link>
                    <div className="max-w-2xl space-y-5">
                        <p className="text-sm font-semibold tracking-[0.16em] text-teal-800 uppercase dark:text-teal-300">
                            A safe, synthetic workspace
                        </p>
                        <h1
                            id="demo-heading"
                            className="text-4xl font-semibold tracking-tight text-balance sm:text-6xl"
                        >
                            Follow the work from first contact to community
                            impact.
                        </h1>
                        <p className="max-w-xl text-lg leading-8 text-slate-600 dark:text-slate-300">
                            Step into several staff perspectives across two
                            fictional human-services organisations. No account,
                            setup, or real personal information is required.
                        </p>
                    </div>
                    <div
                        className="flex max-w-xl items-center gap-3 text-sm text-slate-600 dark:text-slate-300"
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
                                <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-teal-800 text-xs font-semibold text-white">
                                    {index + 1}
                                </span>
                                <span className="leading-tight">{step}</span>
                                {index < 2 && (
                                    <span
                                        className="hidden h-px flex-1 bg-teal-700/30 sm:block"
                                        aria-hidden="true"
                                    />
                                )}
                            </div>
                        ))}
                    </div>
                </section>

                <section
                    aria-label="Prepare demo"
                    className="rounded-2xl border border-slate-900/10 bg-white/80 p-8 shadow-sm backdrop-blur dark:border-white/10 dark:bg-slate-900/80"
                >
                    <div className="space-y-7">
                        <div className="space-y-3">
                            <div className="flex size-11 items-center justify-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                <ShieldCheck
                                    className="size-6"
                                    aria-hidden="true"
                                />
                            </div>
                            <h2 className="text-2xl font-semibold tracking-tight">
                                Your isolated evaluation space
                            </h2>
                            <p className="leading-7 text-slate-600 dark:text-slate-300">
                                The workspace lasts up to {lifetimeHours} hours.
                                Uploads, invitations, outbound messages,
                                payments, and custom domains stay disabled.
                            </p>
                        </div>

                        <ul className="space-y-3 text-sm text-slate-700 dark:text-slate-200">
                            <li className="flex gap-3">
                                <span className="mt-2 size-1.5 shrink-0 rounded-full bg-teal-700" />
                                Fictional people, cases, donations, volunteers,
                                and outcomes
                            </li>
                            <li className="flex gap-3">
                                <span className="mt-2 size-1.5 shrink-0 rounded-full bg-teal-700" />
                                Role-specific permissions and realistic safety
                                boundaries
                            </li>
                            <li className="flex gap-3">
                                <span className="mt-2 size-1.5 shrink-0 rounded-full bg-teal-700" />
                                A clean reset whenever you want to start again
                            </li>
                        </ul>

                        <Form {...store.form()} className="space-y-3">
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="w-full bg-teal-800 text-white hover:bg-teal-700 focus-visible:ring-teal-700"
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

                        <p className="flex items-center justify-center gap-2 text-center text-xs text-slate-500 dark:text-slate-400">
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
