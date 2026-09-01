import { Form, usePage } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { destroy } from '@/routes/demo';

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
            <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-center gap-x-3 gap-y-1 text-sm font-medium">
                <FlaskConical className="size-4" aria-hidden="true" />
                <span>
                    Synthetic demo data · uploads, invitations, external
                    messages, payments, and domains are disabled
                    {sandbox.expiresAt
                        ? ` · expires ${new Date(sandbox.expiresAt).toLocaleString()}`
                        : null}
                </span>
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
        </div>
    );
}
