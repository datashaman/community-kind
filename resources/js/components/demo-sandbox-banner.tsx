import { usePage } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';

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
            <div className="mx-auto flex max-w-7xl items-center justify-center gap-2 text-sm font-medium">
                <FlaskConical className="size-4" aria-hidden="true" />
                Synthetic demo data · uploads, invitations, external messages,
                payments, and domains are disabled
                {sandbox.expiresAt
                    ? ` · expires ${new Date(sandbox.expiresAt).toLocaleString()}`
                    : null}
            </div>
        </div>
    );
}
