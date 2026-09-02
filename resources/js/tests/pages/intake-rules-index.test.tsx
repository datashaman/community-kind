import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ComponentProps } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import IntakeRulesIndex from '@/pages/intake-rules/index';

vi.mock('@inertiajs/react', () => ({
    Form: ({ children, ...props }: ComponentProps<'form'>) => (
        <form {...props}>{children}</form>
    ),
    Head: () => null,
    useForm: <T,>(data: T) => ({
        data,
        errors: {},
        post: vi.fn(),
        processing: false,
        reset: vi.fn(),
        setData: vi.fn(),
    }),
    usePage: () => ({
        props: { currentOrganisation: { slug: 'harbour-kind' } },
    }),
}));

vi.mock('@/routes/intake-rules', () => ({
    activate: { form: () => ({ action: '/activate' }) },
    index: vi.fn(),
    store: { url: () => '/store' },
}));

const rule = {
    id: 'rule-1',
    version: 2,
    status: 'active',
    requiredFields: ['party_uuid', 'email'],
    defaultUrgency: 'urgent',
    restrictedAccessBypassAllowed: false,
    activatedAt: '2026-08-01T00:00:00Z',
    canActivate: false,
};

const renderPage = (rules = [rule]) =>
    render(
        <IntakeRulesIndex
            rules={rules}
            fixedRequiredFields={[
                { value: 'party_uuid', label: 'Party identity' },
                { value: 'program_id', label: 'Program' },
            ]}
            urgencies={[
                { value: 'routine', label: 'Routine' },
                { value: 'urgent', label: 'Urgent' },
            ]}
        />,
    );

afterEach(cleanup);

describe('intake rules index', () => {
    it('shows the versions without a draft form open', () => {
        renderPage();

        /*
         * The page used to lead with the create form, so the versions it
         * exists to show were below the fold.
         */
        expect(screen.queryByRole('form')).not.toBeInTheDocument();
        expect(
            screen.queryByRole('checkbox', { name: /Email address/ }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('v2')).toBeInTheDocument();
    });

    it('opens the draft panel on request and focuses it', async () => {
        renderPage();

        await userEvent.click(
            screen.getByRole('button', { name: 'New version' }),
        );

        const email = screen.getByRole('checkbox', { name: /Email address/ });
        expect(email).toHaveFocus();
        expect(email).toBeChecked();
    });

    it('states the fixed safeguards once, without a control for them', async () => {
        renderPage();
        await userEvent.click(
            screen.getByRole('button', { name: 'New version' }),
        );

        const fixed = screen
            .getByText('What intake rules cannot change')
            .closest('details')!;

        /*
         * This was a disabled, permanently unchecked checkbox: impossible to
         * operate, and an empty box says the safeguard is off when it is on.
         */
        expect(within(fixed).queryByRole('checkbox')).not.toBeInTheDocument();
        expect(fixed).toHaveTextContent(/Party identity, Program/);
        expect(fixed).toHaveTextContent(/cannot weaken restricted case access/);
    });

    it('does not repeat the fixed safeguards on every version', () => {
        renderPage();

        expect(screen.getByRole('listitem')).not.toHaveTextContent(
            /remain required/,
        );
    });

    it('teaches what applies while no version exists', () => {
        renderPage([]);

        expect(
            screen.getByText(/Platform defaults stay in force/),
        ).toHaveTextContent(/routine urgency/);
    });
});
