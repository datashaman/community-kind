import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
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

const renderPage = () =>
    render(
        <IntakeRulesIndex
            rules={[]}
            fixedRequiredFields={[
                { value: 'consent', label: 'Consent record' },
            ]}
            urgencies={[{ value: 'routine', label: 'Routine' }]}
        />,
    );

afterEach(cleanup);

describe('intake rules index', () => {
    it('states the restricted-access safeguard rather than drawing a control for it', () => {
        renderPage();

        const safeguard = screen
            .getByText(/Restricted-access bypass/)
            .closest('div')!;

        /*
         * This was a disabled, permanently unchecked checkbox: impossible to
         * operate, and an empty box says the safeguard is off when it is on.
         */
        expect(
            within(safeguard).queryByRole('checkbox'),
        ).not.toBeInTheDocument();
        expect(safeguard).toHaveTextContent(
            /cannot weaken restricted case access/,
        );
    });

    it('keeps the checkboxes that do change the draft', () => {
        renderPage();

        expect(
            screen.getByRole('checkbox', { name: /Email address/ }),
        ).toBeEnabled();
    });
});
