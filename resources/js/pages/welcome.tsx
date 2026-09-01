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
    'https://github.com/datashaman/community-kind/tree/main/docs';

const operatingModel = [
    [
        'Service delivery',
        'Respond with the right context',
        'Move from intake to assigned support with consent, safety, and programme scope carried through the work.',
        'bg-[#1E7A70]',
    ],
    [
        'Community engagement',
        'Turn participation into relationships',
        'Coordinate events, volunteers, and partner activity without losing the people and purpose behind them.',
        'bg-[#5E8F4E]',
    ],
    [
        'Supporter stewardship',
        'Make every welcome feel joined up',
        'Understand donations, audiences, and journeys while keeping supporter-safe work apart from confidential services.',
        'bg-[#E5A23A]',
    ],
    [
        'Impact evidence',
        'Show what changed—and why',
        'Bring operational signals into accountable impact packs for leaders, funders, partners, and communities.',
        'bg-[#D86A56]',
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
            <div className="min-h-screen bg-[#F4F7F5] text-[#173B57] dark:bg-[#0D1D29] dark:text-[#F4F7F5]">
                <header className="border-b border-[#173B57]/10 dark:border-white/10">
                    <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-6 px-5 py-5 sm:px-8 lg:px-12">
                        <Link
                            href="/"
                            className="inline-flex items-center gap-3 rounded-sm text-lg font-semibold tracking-tight focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70]"
                        >
                            <span
                                className="grid size-8 grid-cols-2 gap-0.5 rounded-full bg-[#173B57] p-2 dark:bg-[#F4F7F5]"
                                aria-hidden="true"
                            >
                                <span className="rounded-full bg-[#1E7A70]" />
                                <span className="rounded-full bg-[#E5A23A]" />
                                <span className="rounded-full bg-[#D86A56]" />
                                <span className="rounded-full bg-[#5E8F4E]" />
                            </span>
                            CommunityKind
                        </Link>
                        <nav
                            aria-label="Primary navigation"
                            className="flex items-center gap-2 sm:gap-5"
                        >
                            <a
                                href="#operating-model"
                                className="hidden rounded-sm text-sm font-medium hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70] sm:inline"
                            >
                                How it connects
                            </a>
                            {auth.user ? (
                                <Link
                                    href={dashboardUrl}
                                    className="rounded-full bg-[#173B57] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#1E7A70] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70] dark:bg-[#F4F7F5] dark:text-[#173B57]"
                                >
                                    Open dashboard
                                </Link>
                            ) : (
                                <Link
                                    href={login()}
                                    className="rounded-full border border-[#173B57]/25 px-4 py-2.5 text-sm font-semibold transition-colors hover:border-[#1E7A70] hover:text-[#1E7A70] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70] dark:border-white/25"
                                >
                                    Staff log in
                                </Link>
                            )}
                        </nav>
                    </div>
                </header>

                <main>
                    <section className="overflow-hidden border-b border-[#173B57]/10 dark:border-white/10">
                        <div className="mx-auto grid max-w-7xl items-center gap-14 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[1.05fr_0.95fr] lg:px-12">
                            <div className="relative z-10 max-w-3xl">
                                <p className="mb-6 flex items-center gap-2 text-sm font-semibold tracking-[0.14em] text-[#1E7A70] uppercase dark:text-[#71C6BC]">
                                    <CircleDot
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Community operations for human services
                                </p>
                                <h1 className="text-5xl leading-[0.98] font-semibold tracking-[-0.055em] text-balance sm:text-7xl lg:text-[4.6rem]">
                                    One shared thread from first contact to{' '}
                                    <span className="font-serif font-normal text-[#1E7A70] italic dark:text-[#71C6BC]">
                                        visible impact.
                                    </span>
                                </h1>
                                <p className="mt-8 max-w-2xl text-lg leading-8 text-[#173B57]/75 sm:text-xl dark:text-[#F4F7F5]/75">
                                    CommunityKind helps local organisations
                                    coordinate services, supporters, volunteers,
                                    and evidence—without flattening the privacy
                                    boundaries between them.
                                </p>
                                <div className="mt-10 flex flex-wrap items-center gap-3">
                                    <Link
                                        href={createDemo()}
                                        className="inline-flex items-center gap-2 rounded-full bg-[#173B57] px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#1E7A70] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70] dark:bg-[#F4F7F5] dark:text-[#173B57]"
                                    >
                                        Explore the demo{' '}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                    <a
                                        href={documentationUrl}
                                        className="inline-flex items-center gap-2 rounded-full px-5 py-3.5 text-sm font-semibold hover:text-[#1E7A70] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70]"
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
                                        className="text-[#173B57]/18 dark:text-white/18"
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
                                        'bg-[#1E7A70]',
                                    ],
                                    [
                                        'A community responds',
                                        'right-[0%] top-[32%]',
                                        'bg-[#5E8F4E]',
                                    ],
                                    [
                                        'Relationships grow',
                                        'left-[18%] top-[58%]',
                                        'bg-[#E5A23A]',
                                    ],
                                    [
                                        'Change becomes visible',
                                        'right-[0%] bottom-[4%]',
                                        'bg-[#D86A56]',
                                    ],
                                ].map(([label, position, color]) => (
                                    <div
                                        key={label}
                                        className={`absolute ${position} flex max-w-48 items-center gap-3 rounded-xl border border-[#173B57]/10 bg-white/90 p-3 shadow-[0_18px_45px_rgba(23,59,87,0.12)] backdrop-blur dark:border-white/10 dark:bg-[#173B57]/90`}
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
                                <p className="text-sm font-semibold tracking-[0.14em] text-[#1E7A70] uppercase dark:text-[#71C6BC]">
                                    One connected operating model
                                </p>
                                <h2
                                    id="operating-model-heading"
                                    className="mt-4 text-4xl leading-tight font-semibold tracking-[-0.035em] text-balance sm:text-5xl"
                                >
                                    The work makes more sense when the thread
                                    stays intact.
                                </h2>
                                <p className="mt-6 text-base leading-7 text-[#173B57]/70 dark:text-[#F4F7F5]/70">
                                    Each team keeps the view and safeguards it
                                    needs. Shared signals travel forward, so
                                    impact is grounded in real work instead of
                                    assembled at reporting time.
                                </p>
                            </div>
                            <ol className="relative border-l-2 border-[#173B57]/10 dark:border-white/10">
                                {operatingModel.map(
                                    ([label, title, body, color], index) => (
                                        <li
                                            key={label}
                                            className="relative grid gap-3 border-b border-[#173B57]/10 py-7 pl-8 last:border-b-0 sm:grid-cols-[10rem_1fr] sm:gap-8 dark:border-white/10"
                                        >
                                            <span
                                                className={`absolute top-9 -left-[7px] size-3 rounded-full ring-4 ring-[#F4F7F5] dark:ring-[#0D1D29] ${color}`}
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <span className="text-xs font-semibold tracking-[0.12em] text-[#173B57]/55 uppercase dark:text-[#F4F7F5]/55">
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
                                                <h3 className="text-xl font-semibold tracking-tight">
                                                    {title}
                                                </h3>
                                                <p className="mt-2 leading-7 text-[#173B57]/70 dark:text-[#F4F7F5]/70">
                                                    {body}
                                                </p>
                                            </div>
                                        </li>
                                    ),
                                )}
                            </ol>
                        </div>
                    </section>

                    <section className="bg-[#173B57] text-[#F4F7F5]">
                        <div className="mx-auto grid max-w-7xl gap-14 px-5 py-20 sm:px-8 sm:py-28 lg:grid-cols-2 lg:px-12">
                            <div>
                                <LockKeyhole
                                    className="size-9 text-[#71C6BC]"
                                    aria-hidden="true"
                                />
                                <h2 className="mt-6 text-4xl leading-tight font-semibold tracking-[-0.035em] sm:text-5xl">
                                    Privacy is part of the operating model.
                                </h2>
                                <p className="mt-6 max-w-xl text-lg leading-8 text-[#F4F7F5]/70">
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
                                        className="flex gap-3 border-t border-white/15 pt-4 leading-7 text-[#F4F7F5]/85"
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
                        <div className="grid gap-12 border-b border-[#173B57]/15 pb-20 lg:grid-cols-[1fr_0.8fr] dark:border-white/15">
                            <div>
                                <p className="text-sm font-semibold tracking-[0.14em] text-[#1E7A70] uppercase dark:text-[#71C6BC]">
                                    Open by design
                                </p>
                                <h2 className="mt-4 max-w-3xl text-4xl leading-tight font-semibold tracking-[-0.035em] sm:text-5xl">
                                    Inspect the decisions, not just the demo.
                                </h2>
                                <p className="mt-6 max-w-2xl text-lg leading-8 text-[#173B57]/70 dark:text-[#F4F7F5]/70">
                                    The source, licence, domain decisions, and
                                    technical documentation are public. That
                                    makes the platform easier to evaluate,
                                    challenge, adapt, and improve together.
                                </p>
                            </div>
                            <div className="flex flex-col justify-end gap-3">
                                <a
                                    href={documentationUrl}
                                    className="inline-flex items-center justify-between gap-4 border-t border-[#173B57]/15 py-4 font-semibold hover:text-[#1E7A70] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70] dark:border-white/15"
                                >
                                    Read the documentation{' '}
                                    <BookOpen
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </a>
                                <a
                                    href="https://github.com/datashaman/community-kind"
                                    className="inline-flex items-center justify-between gap-4 border-t border-[#173B57]/15 py-4 font-semibold hover:text-[#1E7A70] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70] dark:border-white/15"
                                >
                                    Inspect the repository{' '}
                                    <GitFork
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </a>
                                <SourceAndLicenceLink className="inline-flex items-center justify-between gap-4 border-y border-[#173B57]/15 py-4 font-semibold hover:text-[#1E7A70] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70] dark:border-white/15" />
                            </div>
                        </div>
                        <div className="grid items-end gap-10 pt-20 lg:grid-cols-[1fr_auto]">
                            <div>
                                <p className="text-sm font-semibold tracking-[0.14em] text-[#D86A56] uppercase dark:text-[#F29A88]">
                                    Honest about readiness
                                </p>
                                <h2 className="mt-4 max-w-3xl text-4xl leading-tight font-semibold tracking-[-0.035em] sm:text-5xl">
                                    Evaluate the shape of the work—safely.
                                </h2>
                                <p className="mt-6 max-w-3xl leading-7 text-[#173B57]/70 dark:text-[#F4F7F5]/70">
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
                                className="inline-flex items-center justify-center gap-2 rounded-full bg-[#D86A56] px-6 py-3.5 text-sm font-semibold text-white transition-colors hover:bg-[#173B57] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#D86A56] dark:hover:bg-[#F4F7F5] dark:hover:text-[#173B57]"
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

                <footer className="border-t border-[#173B57]/10 dark:border-white/10">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-5 px-5 py-8 text-sm sm:px-8 lg:px-12">
                        <p>Built in the open for stronger community work.</p>
                        <div className="flex flex-wrap gap-5">
                            <a
                                href={documentationUrl}
                                className="hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70]"
                            >
                                Documentation
                            </a>
                            <Link
                                href={login()}
                                className="hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70]"
                            >
                                Staff log in
                            </Link>
                            <SourceAndLicenceLink className="hover:underline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#1E7A70]" />
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
