import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ReportingPublicationIndex from '@/pages/reporting-publication/index';

// Radix's Checkbox measures itself; jsdom has no ResizeObserver.
globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
} as unknown as typeof ResizeObserver;

let formData = {
    public_metric_ids: [] as string[],
    pack_metric_ids: [] as string[],
};

vi.mock('@inertiajs/react', () => ({
    Form: ({ children }: { children?: React.ReactNode }) => <>{children}</>,
    Head: () => null,
    useForm: () => ({
        clearErrors: vi.fn(),
        data: formData,
        errors: {},
        post: vi.fn(),
        processing: false,
        setData: vi.fn(),
    }),
    usePage: () => ({
        props: { currentOrganisation: { slug: 'harbour-kind' } },
    }),
}));

vi.mock('@/routes/reporting-publication', () => ({
    activate: { form: () => ({}) },
    index: () => '/reporting-publication',
    store: { url: () => '/reporting-publication' },
}));

const metrics = [
    {
        id: 'households-supported',
        version: '1',
        category: 'service',
        domain: 'service',
        label: 'Households supported',
        description: 'Distinct households with a completed service episode.',
        formula: 'count',
        unit: 'count',
        dimensions: [],
    },
    {
        id: 'referrals-accepted',
        version: '1',
        category: 'service',
        domain: 'intake',
        label: 'Referrals accepted',
        description: 'Referrals reaching accepted state within the period.',
        formula: 'count',
        unit: 'count',
        dimensions: [],
    },
];

const selected = (id: string, label: string, available = true) => ({
    id,
    label,
    domain: 'service',
    unit: 'count',
    available,
});

beforeEach(() => {
    formData = { public_metric_ids: [], pack_metric_ids: [] };
});

afterEach(cleanup);

describe('reporting publication editor', () => {
    it('lists each metric once rather than once per destination', () => {
        render(<ReportingPublicationIndex metrics={metrics} versions={[]} />);

        for (const metric of metrics) {
            expect(screen.getAllByText(metric.label)).toHaveLength(1);
            expect(screen.getAllByText(metric.description)).toHaveLength(1);
        }
    });

    it('offers a separately named checkbox per destination on each row', () => {
        render(<ReportingPublicationIndex metrics={metrics} versions={[]} />);

        expect(
            screen.getByRole('checkbox', {
                name: 'Publish Households supported on the Public impact page',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('checkbox', {
                name: 'Include Households supported in Approved reporting packs',
            }),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('checkbox')).toHaveLength(
            metrics.length * 2,
        );
    });

    it('summarises each destination instead of re-listing the selections', () => {
        formData = {
            public_metric_ids: ['households-supported'],
            pack_metric_ids: ['households-supported', 'referrals-accepted'],
        };

        render(<ReportingPublicationIndex metrics={metrics} versions={[]} />);

        const summary = screen.getByText(/on the public page/);

        expect(summary).toHaveTextContent('1 on the public page');
        expect(summary).toHaveTextContent('2 in reporting packs');
    });

    it('warns when the search hides metrics that are still selected', () => {
        formData = {
            public_metric_ids: ['households-supported'],
            pack_metric_ids: [],
        };

        render(
            <ReportingPublicationIndex metrics={[metrics[1]!]} versions={[]} />,
        );

        expect(screen.getByText(/on the public page/)).toHaveTextContent(
            '1 selected metric hidden by the current search',
        );
    });
});

describe('reporting publication version history', () => {
    it('shows a metric in both destinations on a single row', () => {
        render(
            <ReportingPublicationIndex
                metrics={metrics}
                versions={[
                    {
                        id: 'version-1',
                        version: 1,
                        status: 'draft',
                        publicMetrics: [
                            selected(
                                'households-supported',
                                'Households supported',
                            ),
                        ],
                        packMetrics: [
                            selected(
                                'households-supported',
                                'Households supported',
                            ),
                            selected(
                                'referrals-accepted',
                                'Referrals accepted',
                            ),
                        ],
                        activatedAt: null,
                        hasUnavailableMetrics: false,
                        canActivate: false,
                    },
                ]}
            />,
        );

        /*
         * Once in the editor table and once in the version matrix. Previously
         * the version card listed it under both destination headings, so a
         * metric published to both appeared twice within the same card.
         */
        expect(screen.getAllByText('Households supported')).toHaveLength(2);

        const versionTable = screen.getByRole('table', {
            name: /version 1/i,
        });

        expect(versionTable).toHaveTextContent(
            'Households supported is published on Public impact page',
        );
        expect(versionTable).toHaveTextContent(
            'Referrals accepted is not published on Public impact page',
        );
    });
});
