import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login } from '@/routes';
import SourceAndLicenceLink from '@/components/source-and-licence-link';

export default function Welcome() {
    const { auth, currentOrganisation } = usePage().props;
    const dashboardUrl = currentOrganisation
        ? dashboard(currentOrganisation.slug)
        : '/';

    return (
        <>
            <Head title="Community impact, connected" />
            <div className="bg-background text-foreground flex min-h-screen flex-col">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
                    <Link href="/" className="text-lg font-semibold">
                        CommunityKind
                    </Link>
                    <nav
                        aria-label="Account"
                        className="flex items-center gap-3"
                    >
                        {auth.user ? (
                            <Link
                                href={dashboardUrl}
                                className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white dark:bg-white dark:text-neutral-900"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <Link
                                href={login()}
                                className="rounded-md border px-4 py-2 text-sm font-medium"
                            >
                                Staff log in
                            </Link>
                        )}
                    </nav>
                </header>

                <main className="mx-auto flex w-full max-w-5xl flex-1 items-center px-6 py-20">
                    <div className="max-w-3xl">
                        <p className="text-muted-foreground mb-4 text-sm font-medium tracking-wide uppercase">
                            Open-source community operations
                        </p>
                        <h1 className="text-4xl font-semibold tracking-tight sm:text-6xl">
                            Connect local support to measurable community
                            impact.
                        </h1>
                        <p className="text-muted-foreground mt-6 max-w-2xl text-lg leading-8">
                            CommunityKind brings service delivery, supporter
                            engagement, and impact reporting together while
                            keeping sensitive client information deliberately
                            separate.
                        </p>
                        <p className="mt-8 rounded-lg border p-4 text-sm">
                            This is a specification-stage reference
                            implementation using fictional organisations and
                            synthetic data. It is not ready for real client or
                            supporter information.
                        </p>
                    </div>
                </main>

                <footer className="mx-auto flex w-full max-w-5xl flex-wrap gap-5 px-6 py-8 text-sm">
                    <a
                        href="https://github.com/datashaman/community-kind"
                        className="underline underline-offset-4"
                    >
                        Repository
                    </a>
                    <SourceAndLicenceLink className="underline underline-offset-4" />
                </footer>
            </div>
        </>
    );
}
