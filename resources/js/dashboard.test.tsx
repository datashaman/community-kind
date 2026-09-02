import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import type { ComponentProps, ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
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

describe('Service operations dashboard', () => {
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
});
