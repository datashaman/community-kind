import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import type { ComponentProps, ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Dashboard from './pages/dashboard';

vi.mock('@inertiajs/react', () => ({
    Form: ({ children, ...props }: ComponentProps<'form'>) => (
        <form {...props}>{children}</form>
    ),
    Head: () => null,
    Link: ({ children, ...props }: ComponentProps<'a'>) => (
        <a {...props}>{children}</a>
    ),
    usePage: () => ({
        props: { currentOrganisation: { slug: 'harbour-kind' } },
    }),
}));

vi.mock('@/components/pending-invitations-modal', () => ({
    default: ({ children }: { children?: ReactNode }) => children ?? null,
}));

const countMetric = (category: string, id: string, label: string) => ({
    definition: {
        id,
        version: '2026.4',
        category,
        label,
        description: 'A fictional figure.',
        formula: 'count(things)',
        unit: 'count',
    },
    value: 0,
    availability: 'available' as const,
    sampleSize: null,
    comparison: null,
});

const impact = {
    registryVersion: '2026.4',
    fictional: true,
    freshAt: '2026-09-02T03:57:23Z',
    timezone: 'Africa/Johannesburg',
    currency: 'ZAR',
    period: { start: '2026-09-01', endExclusive: '2026-09-03' },
    filters: {},
    minimumCohort: 5,
    metrics: [
        countMetric('input', 'service.requests_received', 'Requests received'),
        countMetric(
            'activity',
            'service.case_interactions',
            'Case interactions',
        ),
        countMetric('output', 'service.cases_closed', 'Cases closed'),
        countMetric(
            'outcome',
            'service.outcomes_improved',
            'Outcomes improved',
        ),
    ],
    options: {
        programs: [],
        areas: [],
        locations: [],
        cohorts: [],
        campaigns: [],
    },
};

const emptyOperations = {
    programs: [],
    selectedProgramId: null,
    counts: {
        caseload: 0,
        waitlist: 0,
        overdue: 0,
        risks: 0,
        referrals: 0,
    },
    caseload: [],
    waitlist: [],
    overdue: [],
    risks: [],
    referrals: [],
};

const impactSection = () =>
    screen.getByRole('region', { name: 'Reconciled impact' });

describe('Service operations dashboard', () => {
    afterEach(cleanup);

    it('exposes labelled, keyboard-focusable controls and work links', () => {
        render(
            <Dashboard
                serviceOperations={{
                    programs: [{ id: 7, name: 'Family Support' }],
                    selectedProgramId: null,
                    counts: {
                        caseload: 1,
                        waitlist: 0,
                        overdue: 0,
                        risks: 0,
                        referrals: 0,
                    },
                    caseload: [
                        {
                            id: 'case-1',
                            program: 'Family Support',
                            status: 'active',
                        },
                    ],
                    waitlist: [],
                    overdue: [],
                    risks: [],
                    referrals: [],
                }}
            />,
        );

        expect(
            screen.getByRole('combobox', { name: 'Filter by program' }),
        ).toHaveAccessibleName('Filter by program');
        expect(
            screen.getByRole('region', { name: 'Work queues' }),
        ).toBeVisible();
        expect(screen.getAllByText('Queue clear')).toHaveLength(4);

        /*
         * Each queue was listed twice: once as a count card, then again as the
         * card holding its rows. The count now sits in the row card's header,
         * so every queue name must appear exactly once.
         */
        for (const label of [
            'Active caseload',
            'Waitlist',
            'Overdue actions',
            'Unresolved risks',
            'External referrals',
        ]) {
            expect(screen.getAllByText(label)).toHaveLength(1);
        }
        expect(screen.getByText('1 open')).toBeVisible();

        const interactiveElements = [
            ...screen.getAllByRole('button'),
            ...screen.getAllByRole('link'),
            screen.getByRole('combobox'),
        ];

        for (const element of interactiveElements) {
            expect(element.tabIndex).toBe(0);
            element.focus();
            expect(element).toHaveFocus();
        }
    });

    /*
     * A card is a label and a figure. It used to also carry a description, the
     * registry code, the formula and a prior-period sentence, under a heading
     * naming the category — five things where two were wanted.
     */
    it('shows a label and a figure on each card, nothing else', () => {
        render(
            <Dashboard serviceOperations={emptyOperations} impact={impact} />,
        );

        const cards = within(impactSection()).getAllByRole('listitem');
        expect(cards).toHaveLength(4);

        const card = cards[0];
        expect(card).toHaveTextContent('Requests received');
        expect(card).toHaveTextContent('0');
        expect(card).not.toHaveTextContent('service.requests_received');
        expect(card).not.toHaveTextContent('count(requests)');
        expect(card).not.toHaveTextContent('2026.4');
        expect(card).not.toHaveTextContent('A fictional figure.');

        /*
         * The category names the metric on its own card rather than heading a
         * column, so the section keeps its one h2 and gains no h3s.
         */
        expect(card).toHaveTextContent('Input');
        for (const category of ['Input', 'Activity', 'Output', 'Outcome']) {
            expect(
                within(impactSection()).queryByRole('heading', {
                    name: category,
                }),
            ).toBeNull();
        }
    });

    it('orders the cards by the logic model', () => {
        render(
            <Dashboard serviceOperations={emptyOperations} impact={impact} />,
        );

        expect(
            within(impactSection())
                .getAllByRole('listitem')
                .map((card) => card.textContent),
        ).toEqual([
            expect.stringContaining('Input'),
            expect.stringContaining('Activity'),
            expect.stringContaining('Output'),
            expect.stringContaining('Outcome'),
        ]);
    });

    /*
     * The provenance the PRD requires on the dashboard is still here, once,
     * behind a disclosure — not restated on every card.
     */
    it('keeps the definitions reachable without putting them on the cards', () => {
        render(
            <Dashboard serviceOperations={emptyOperations} impact={impact} />,
        );

        const definitions = within(impactSection())
            .getByText('How these figures are calculated')
            .closest('details');
        expect(definitions).toHaveTextContent('service.requests_received');
        expect(definitions).toHaveTextContent('count(things)');
        expect(definitions).toHaveTextContent('2026.4');
        expect(definitions).toHaveTextContent('A fictional figure.');
    });

    /*
     * Net raised is the only currency metric, so it used to draw a full-width
     * bar whatever its value — R50 and R5,000,000 rendered identically.
     */
    it('draws no bar for a value with nothing on its scale to compare against', () => {
        render(
            <Dashboard
                serviceOperations={emptyOperations}
                impact={{
                    ...impact,
                    metrics: [
                        { ...countMetric('input', 'a', 'Alpha'), value: 40 },
                        { ...countMetric('output', 'b', 'Beta'), value: 10 },
                        {
                            ...countMetric('output', 'c', 'Net raised'),
                            value: 5000,
                            definition: {
                                ...countMetric('output', 'c', 'Net raised')
                                    .definition,
                                unit: 'currency',
                            },
                        },
                    ],
                }}
            />,
        );

        const chart = screen
            .getByRole('heading', { name: 'Presentation chart' })
            .closest('figure');
        const bars = [...(chart?.querySelectorAll('.bg-primary') ?? [])];

        /* Two counts share a scale; the lone currency value does not. */
        expect(bars).toHaveLength(2);
        expect(bars[0]).toHaveStyle({ width: '100%' });
        expect(bars[1]).toHaveStyle({ width: '25%' });
        expect(chart).toHaveTextContent('Counts · scaled to 40');
        expect(chart).toHaveTextContent('ZAR');
        expect(chart).not.toHaveTextContent('ZAR · scaled to');
    });

    it('draws no bar for a zero rather than a misleading sliver', () => {
        render(
            <Dashboard serviceOperations={emptyOperations} impact={impact} />,
        );

        const chart = screen
            .getByRole('heading', { name: 'Presentation chart' })
            .closest('figure');
        expect(chart?.querySelectorAll('.bg-primary')).toHaveLength(0);
    });

    /*
     * Suppression only applies once a service area, location or cohort filter
     * is set. The old wording named neither the trigger nor the reason.
     */
    it('says what triggers suppression and why', () => {
        render(
            <Dashboard serviceOperations={emptyOperations} impact={impact} />,
        );

        const section = impactSection();
        expect(section).toHaveTextContent(
            'Filtering by service area, location, or cohort',
        );
        expect(section).toHaveTextContent('can identify the people in it');
        expect(section).not.toHaveTextContent('drill-down');
    });

    /*
     * `sr-only` clips an absolutely positioned box, but a table's caption is
     * laid out outside that box and escapes the clip. Putting the class on the
     * table left the caption showing through beneath the chart.
     */
    it('hides the chart data table without leaking its caption', () => {
        const { container } = render(
            <Dashboard serviceOperations={emptyOperations} impact={impact} />,
        );

        const caption = container.querySelector('caption');
        expect(caption).toHaveTextContent(
            'Nonvisual equivalent of the impact chart',
        );
        expect(caption?.closest('table')).not.toHaveClass('sr-only');
        expect(caption?.closest('.sr-only')).not.toBeNull();
    });
});
