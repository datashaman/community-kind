import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import type { ComponentProps } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ImpactSnapshotsIndex from '@/pages/impact-snapshots/index';

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

vi.mock('@/routes/reporting-publication', () => ({
    index: () => '/harbour-kind/reporting-publication',
}));

vi.mock('@/routes/impact-snapshots', () => ({
    download: () => ({ url: '/harbour-kind/impact-snapshots/1/download' }),
    index: vi.fn(),
    store: { form: () => ({ action: '/harbour-kind/impact-snapshots' }) },
}));

const snapshot = {
    id: 'snapshot-1',
    audience: 'board',
    registryVersion: '2026.1',
    metricCount: 6,
    approvedAt: '2026-08-01T09:00:00Z',
    publishedAt: null,
};

describe('Impact packs index', () => {
    afterEach(cleanup);

    /*
     * The records are what the page is for. The approval form used to sit
     * permanently above them, so reading the list always meant scrolling past
     * a form nobody had asked for.
     */
    it('shows the records first and the approval form only when asked for', () => {
        render(<ImpactSnapshotsIndex snapshots={[snapshot]} canApprove />);

        expect(
            screen.getByRole('heading', { name: 'Approved snapshots' }),
        ).toBeVisible();
        expect(screen.queryByLabelText('Audience')).not.toBeInTheDocument();

        fireEvent.click(
            screen.getByRole('button', { name: 'Approve snapshot' }),
        );

        expect(screen.getByLabelText('Audience')).toBeVisible();
        expect(screen.getByLabelText('Period start')).toBeVisible();
        expect(screen.getByLabelText('Period end')).toBeVisible();
    });

    it('moves focus into the panel and back out again on cancel', () => {
        render(<ImpactSnapshotsIndex snapshots={[snapshot]} canApprove />);

        const open = screen.getByRole('button', { name: 'Approve snapshot' });
        fireEvent.click(open);
        expect(screen.getByLabelText('Audience')).toHaveFocus();

        fireEvent.click(screen.getByRole('button', { name: 'Cancel' }));
        expect(screen.queryByLabelText('Audience')).not.toBeInTheDocument();
        expect(open).toHaveFocus();
    });

    /*
     * A snapshot can only carry metrics an active reporting configuration has
     * approved for release. The page used to offer the action anyway and fail
     * on submit with a 500 naming a LogicException.
     */
    it('explains the missing configuration instead of offering an action that fails', () => {
        render(<ImpactSnapshotsIndex snapshots={[]} canApprove={false} />);

        expect(
            screen.queryByRole('button', { name: 'Approve snapshot' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText('No reporting configuration is active'),
        ).toBeVisible();
        expect(
            screen.getByRole('link', { name: 'Open Reporting publication' }),
        ).toHaveAttribute('href', '/harbour-kind/reporting-publication');
    });

    it('teaches the interface when nothing has been approved', () => {
        render(<ImpactSnapshotsIndex snapshots={[]} canApprove />);

        expect(screen.getByText('No snapshots approved yet')).toBeVisible();
        expect(
            screen.getByRole('button', { name: 'Approve snapshot' }),
        ).toBeVisible();
    });
});
