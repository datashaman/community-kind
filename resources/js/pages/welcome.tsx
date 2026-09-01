import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    Check,
    CircleDot,
    GitFork,
    LockKeyhole,
} from 'lucide-react';
import SourceAndLicenceLink from '@/components/source-and-licence-link';
import { dashboard, login } from '@/routes';
import { create as createDemo } from '@/routes/demo';

const documentationUrl =
    'https://github.com/datashaman/community-kind/blob/main/docs/index.md';

const operatingModel = [
    [
        'Service delivery',
        'Respond with the right context',
        'Move from intake to assigned support with consent, safety, and programme scope carried through the work.',
        'bg-service',
    ],
    [
        'Community engagement',
        'Turn participation into relationships',
        'Coordinate events, volunteers, and partner activity without losing the people and purpose behind them.',
        'bg-leaf',
    ],
    [
        'Supporter stewardship',
        'Make every welcome feel joined up',
        'Understand donations, audiences, and journeys while keeping supporter-safe work apart from confidential services.',
        'bg-saffron',
    ],
    [
        'Impact evidence',
        'Show what changed—and why',
        'Bring operational signals into accountable impact packs for leaders, funders, partners, and communities.',
        'bg-coral',
    ],
] as const;

export default function Welcome() {
    const { auth, currentOrganisation } = usePage().props;
    const dashboardUrl = currentOrganisation
        ? dashboard(currentOrganisation.slug)
        : '/';

    return (
        <>
            <Head title="Community operations, connected" />
            <div className="bg-cloud text-civic dark:text-cloud min-h-screen dark:bg-[#0D1D29]">
                <header className="border-civic/10 border-b dark:border-white/10">
                    <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-12">
                        <Link
                            href="/"
                            className="focus-visible:outline-service inline-flex items-center gap-3 rounded-sm text-lg font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4"
                        >
                            <span
                                className="bg-civic dark:bg-cloud grid size-8 grid-cols-2 gap-0.5 rounded-full p-2"
                                aria-hidden="true"
                            >
                                <span className="bg-service rounded-full" />
                                <span className="bg-saffron rounded-full" />
                                <span className="bg-coral rounded-full" />
                                <span className="bg-leaf rounded-full" />
                            </span>
                            CommunityKind
                        </Link>
                        <nav
                            aria-label="Primary navigation"
                            className="flex items-center gap-2 sm:gap-5"
                        >
                            <a
                                href="#operating-model"
                                className="focus-visible:outline-service hidden rounded-sm text-sm font-medium hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 sm:inline"
                            >
                                How it connects
                            </a>
                            {auth.user ? (
                                <Link
                                    href={dashboardUrl}
                                    className="bg-civic hover:bg-service focus-visible:outline-service dark:bg-cloud dark:text-civic rounded-full px-4 py-2.5 text-sm font-semibold text-white transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                                >
                                    Open dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="border-civic/25 hover:border-service hover:text-service focus-visible:outline-service rounded-full border px-4 py-2.5 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-4 dark:border-white/25"
                                >
                                    Staff log in
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="border-civic/10 overflow-hidden border-b dark:border-white/10">
                        <div className="mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1.05fr_0.95fr] lg:px-12">
                            <div className="relative z-10 max-w-3xl">
                                <p className="text-service mb-6 flex items-center gap-2 text-sm font-semibold tracking-[0.14em] uppercase dark:text-[#71C6BC]">
                                    <CircleDot
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Community operations for human services
                                </p>
                                <h1 className="font-display text-5xl leading-[1.04] font-semibold tracking-[-0.02em] text-balance sm:text-7xl lg:text-[4.6rem]">
                                    One shared thread from first contact to{' '}
                                    <span className="text-service font-medium italic dark:text-[#71C6BC]">
                                        visible impact.
                                    </span>
                                </h1>
                                <p className="text-civic/75 dark:text-cloud/75 mt-8 max-w-2xl text-lg leading-8 sm:text-xl">
                                    CommunityKind helps local organisations
                                    coordinate services, supporters, volunteers,
                                    and evidence—without flattening the privacy
                                    boundaries between them.
                                </p>
                                <div className="mt-10 flex flex-wrap items-center gap-3">
                                    <Link
                                        href={createDemo()}
                                        className="bg-civic hover:bg-service focus-visible:outline-service dark:bg-cloud dark:text-civic inline-flex items-center gap-2 rounded-full px-6 py-3.5 text-sm font-semibold text-white transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                                    >
                                        Explore the demo{' '}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                    <a
                                        href={documentationUrl}
                                        className="hover:text-service focus-visible:outline-service inline-flex items-center gap-2 rounded-full px-5 py-3.5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-4"
                                    >
                                        Read the documentation{' '}
                                        <BookOpen
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </a>
                                </div>
                            </div>

                            <div
                                className="relative mx-auto aspect-[4/5] w-full max-w-lg"
                                aria-label="A connected path through service delivery, engagement, stewardship, and impact"
                                role="img"
                            >
                                <svg
                                    viewBox="0 0 480 600"
                                    className="absolute inset-0 size-full"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M76 76 C330 65 148 218 376 228 C470 232 418 386 211 376 C80 370 72 492 389 524"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="3"
                                        className="text-civic/18 dark:text-white/18"
                                    />
                                    <path
                                        d="M76 76 C330 65 148 218 376 228"
                                        fill="none"
                                        stroke="#1E7A70"
                                        strokeWidth="8"
                                        strokeLinecap="round"
                                    />
                                    <path
                                        d="M376 228 C470 232 418 386 211 376"
                                        fill="none"
                                        stroke="#5E8F4E"
                                        strokeWidth="8"
                                        strokeLinecap="round"
                                    />
                                    <path
                                        d="M211 376 C80 370 72 492 389 524"
                                        fill="none"
                                        stroke="#E5A23A"
                                        strokeWidth="8"
                                        strokeLinecap="round"
                                    />
                                </svg>
                                {[
                                    [
                                        'A person asks for support',
                                        'left-[3%] top-[5%]',
                                        'bg-service',
                                    ],
                                    [
                                        'A community responds',
                                        'right-[0%] top-[32%]',
                                        'bg-leaf',
                                    ],
                                    [
                                        'Relationships grow',
                                        'left-[18%] top-[58%]',
                                        'bg-saffron',
                                    ],
                                    [
                                        'Change becomes visible',
                                        'right-[0%] bottom-[4%]',
                                        'bg-coral',
                                    ],
                                ].map(([label, position, color]) => (
                                    <div
                                        key={label}
                                        className={`absolute ${position} border-civic/10 dark:bg-civic/90 flex max-w-48 items-center gap-3 rounded-xl border bg-white/90 p-3 shadow-[0_18px_45px_rgba(23,59,87,0.12)] backdrop-blur dark:border-white/10`}
                                    >
                                        <span
                                            className={`size-3 shrink-0 rounded-full ${color}`}
                                            aria-hidden="true"
                                        />
                                        <span className="text-sm leading-5 font-semibold">
                                            {label}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section
                        id="operating-model"
                        aria-labelledby="operating-model-heading"
                        className="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-28 lg:px-12"
                    >
                        <div className="grid gap-10 lg:grid-cols-[0.7fr_1.3fr] lg:gap-16">
                            <div>
                                <p className="text-service text-sm font-semibold tracking-[0.14em] uppercase dark:text-[#71C6BC]">
                                    One connected operating model
                                </p>
                                <h2
                                    id="operating-model-heading"
                                    className="font-display mt-4 text-4xl leading-tight font-semibold tracking-[-0.015em] text-balance sm:text-5xl"
                                >
                                    The work makes more sense when the thread
                                    stays intact.
                                </h2>
                                <p className="text-civic/70 dark:text-cloud/70 mt-6 text-base leading-7">
                                    Each team keeps the view and safeguards it
                                    needs. Shared signals travel forward, so
                                    impact is grounded in real work instead of
                                    assembled at reporting time.
                                </p>
                            </div>
                            <ol className="border-civic/10 relative border-l-2 dark:border-white/10">
                                {operatingModel.map(
                                    ([label, title, body, color], index) => (
                                        <li
                                            key={label}
                                            className="border-civic/10 relative grid gap-3 border-b py-7 pl-8 last:border-b-0 sm:grid-cols-[10rem_1fr] sm:gap-8 dark:border-white/10"
                                        >
                                            <span
                                                className={`ring-cloud absolute top-9 -left-[7px] size-3 rounded-full ring-4 dark:ring-[#0D1D29] ${color}`}
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <span className="text-civic/55 dark:text-cloud/55 text-xs font-semibold tracking-[0.12em] uppercase">
                                                    {String(index + 1).padStart(
                                                        2,
                                                        '0',
                                                    )}
                                                </span>
                                                <p className="mt-1 text-sm font-semibold">
                                                    {label}
                                                </p>
                                            </div>
                                            <div>
                                                <h3 className="font-display text-xl font-semibold">
                                                    {title}
                                                </h3>
                                                <p className="text-civic/70 dark:text-cloud/70 mt-2 leading-7">
                                                    {body}
                                                </p>
                                            </div>
                                        </li>
                                    ),
                                )}
                            </ol>
                        </div>
                    </section>

                    <section className="bg-civic text-cloud">
                        <div className="mx-auto grid max-w-7xl gap-14 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-2 lg:px-12">
                            <div>
                                <LockKeyhole
                                    className="size-9 text-[#71C6BC]"
                                    aria-hidden="true"
                                />
                                <h2 className="font-display mt-6 text-4xl leading-tight font-semibold tracking-[-0.015em] sm:text-5xl">
                                    Privacy is part of the operating model.
                                </h2>
                                <p className="text-cloud/70 mt-6 max-w-xl text-lg leading-8">
                                    CommunityKind separates confidential service
                                    delivery from supporter-safe engagement,
                                    then applies role and programme boundaries
                                    before information reaches a screen.
                                </p>
                            </div>
                            <ul className="grid content-start gap-4 sm:grid-cols-2">
                                {[
                                    'Consent and safe-contact context travels with the person.',
                                    'Case access follows assignment and programme scope.',
                                    'Important actions remain visible in an audit history.',
                                    'The public demo uses isolated, expiring synthetic data only.',
                                ].map((item) => (
                                    <li
                                        key={item}
                                        className="text-cloud/85 flex gap-3 border-t border-white/15 pt-4 leading-7"
                                    >
                                        <Check
                                            className="mt-1 size-5 shrink-0 text-[#71C6BC]"
                                            aria-hidden="true"
                                        />
                                        {item}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>

                    <section className="mx-auto max-w-7xl px-5 py-20 sm:px-8 sm:py-28 lg:px-12">
                        <div className="border-civic/15 grid gap-12 border-b pb-20 lg:grid-cols-[1fr_0.8fr] dark:border-white/15">
                            <div>
                                <p className="text-service text-sm font-semibold tracking-[0.14em] uppercase dark:text-[#71C6BC]">
                                    Open by design
                                </p>
                                <h2 className="font-display mt-4 max-w-3xl text-4xl leading-tight font-semibold tracking-[-0.015em] sm:text-5xl">
                                    Inspect the decisions, not just the demo.
                                </h2>
                                <p className="text-civic/70 dark:text-cloud/70 mt-6 max-w-2xl text-lg leading-8">
                                    The source, licence, domain decisions, and
                                    technical documentation are public. That
                                    makes the platform easier to evaluate,
                                    challenge, adapt, and improve together.
                                </p>
                            </div>
                            <div className="flex flex-col justify-end gap-3">
                                <a
                                    href={documentationUrl}
                                    className="border-civic/15 hover:text-service focus-visible:outline-service inline-flex items-center justify-between gap-4 border-t py-4 font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 dark:border-white/15"
                                >
                                    Read the documentation{' '}
                                    <BookOpen
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </a>
                                <a
                                    href="https://github.com/datashaman/community-kind"
                                    className="border-civic/15 hover:text-service focus-visible:outline-service inline-flex items-center justify-between gap-4 border-t py-4 font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 dark:border-white/15"
                                >
                                    Inspect the repository{' '}
                                    <GitFork
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </a>
                                <SourceAndLicenceLink className="border-civic/15 hover:text-service focus-visible:outline-service inline-flex items-center justify-between gap-4 border-y py-4 font-semibold focus-visible:outline-2 focus-visible:outline-offset-4 dark:border-white/15" />
                            </div>
                        </div>
                        <div className="grid items-end gap-10 pt-20 lg:grid-cols-[1fr_auto]">
                            <div>
                                <p className="text-coral text-sm font-semibold tracking-[0.14em] uppercase dark:text-[#F29A88]">
                                    Honest about readiness
                                </p>
                                <h2 className="font-display mt-4 max-w-3xl text-4xl leading-tight font-semibold tracking-[-0.015em] sm:text-5xl">
                                    Evaluate the shape of the work—safely.
                                </h2>
                                <p className="text-civic/70 dark:text-cloud/70 mt-6 max-w-3xl leading-7">
                                    CommunityKind is an active reference
                                    implementation, not yet a production service
                                    for real client or supporter information.
                                    The resettable demo uses fictional
                                    organisations, synthetic records, and
                                    disabled outbound capabilities so you can
                                    inspect it without exposing anyone.
                                </p>
                            </div>
                            <Link
                                href={createDemo()}
                                className="bg-coral hover:bg-civic focus-visible:outline-coral dark:hover:bg-cloud dark:hover:text-civic inline-flex items-center justify-center gap-2 rounded-full px-6 py-3.5 text-sm font-semibold text-white transition-colors focus-visible:outline-2 focus-visible:outline-offset-4"
                            >
                                Start a safe demo{' '}
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                    </section>
                </main>

                <footer className="border-civic/10 border-t dark:border-white/10">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-5 py-8 text-sm sm:px-8 lg:px-12">
                        <p>Built in the open for stronger community work.</p>
                        <div className="flex flex-wrap gap-5">
                            <a
                                href={documentationUrl}
                                className="focus-visible:outline-service hover:underline focus-visible:outline-2 focus-visible:outline-offset-4"
                            >
                                Documentation
                            </a>
                            <Link
                                href={login()}
                                className="focus-visible:outline-service hover:underline focus-visible:outline-2 focus-visible:outline-offset-4"
                            >
                                Staff log in
                            </Link>
                            <SourceAndLicenceLink className="focus-visible:outline-service hover:underline focus-visible:outline-2 focus-visible:outline-offset-4" />
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
