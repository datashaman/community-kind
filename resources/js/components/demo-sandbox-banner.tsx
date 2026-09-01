import { Form, Link, usePage } from '@inertiajs/react';
import { ArrowRight, FlaskConical } from 'lucide-react';
import { destroy } from '@/routes/demo';
import { index as choosePersona } from '@/routes/demo/personas';

export function DemoSandboxBanner() {
    const sandbox = usePage().props.demoSandbox;

    if (!sandbox) {
        return null;
    }

    return (
        <div
            className="border-amber-300 bg-amber-50 px-4 py-2 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
            role="status"
            data-test="demo-sandbox-banner"
        >
            <div className="mx-auto max-w-7xl space-y-2 text-sm">
                <div className="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 font-medium">
                    <FlaskConical className="size-4" aria-hidden="true" />
                    <span>
                        Synthetic demo data · uploads, invitations, external
                        messages, payments, and domains disabled
                        {sandbox.persona
                            ? ` · Viewing as ${sandbox.persona.role} at ${sandbox.persona.organisation}`
                            : null}
                        {sandbox.expiresAt
                            ? ` · expires ${new Date(sandbox.expiresAt).toLocaleString()}`
                            : null}
                    </span>
                    <Link
                        href={choosePersona()}
                        className="rounded-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-900 dark:focus-visible:outline-amber-100"
                    >
                        Change perspective
                    </Link>
                    <Form {...destroy.form()}>
                        {({ processing }) => (
                            <button
                                type="submit"
                                className="rounded-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-900 disabled:opacity-60 dark:focus-visible:outline-amber-100"
                                disabled={processing}
                            >
                                {processing ? 'Resetting…' : 'Reset demo'}
                            </button>
                        )}
                    </Form>
                </div>
                {sandbox.persona && (
                    <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-xs">
                        <span className="font-semibold">Try next:</span>
                        {sandbox.persona.tasks.map((task) => (
                            <Link
                                key={task.href}
                                href={task.href}
                                className="inline-flex items-center gap-1 rounded-sm underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-900 dark:focus-visible:outline-amber-100"
                                title={task.description}
                            >
                                {task.label}
                                <ArrowRight
                                    className="size-3"
                                    aria-hidden="true"
                                />
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
