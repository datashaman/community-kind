import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import type { ComponentProps } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PublicFormsIndex from '@/pages/public-forms/index';

vi.mock('@inertiajs/react', () => ({
    Form: ({ children, ...props }: ComponentProps<'form'>) => (
        <form {...props}>{children}</form>
    ),
    Head: () => null,
    useForm: <T,>(data: T) => ({
        data,
        errors: {},
        clearErrors: vi.fn(),
        post: vi.fn(),
        processing: false,
        setData: vi.fn(),
    }),
    usePage: () => ({
        props: { currentOrganisation: { slug: 'harbour-kind' } },
    }),
}));

vi.mock('@/routes/public-forms', () => ({
    activate: { form: () => ({ action: '/activate' }) },
    index: vi.fn(),
    store: { url: () => '/store' },
}));

const field = (key: string, label: string, required: boolean) => ({
    key,
    label,
    type: 'text',
    required,
    fixedRequired: required,
});

const fields = [
    field('name', 'Name', true),
    field('email', 'Email address', true),
    field('category', 'Category', true),
    field('estimated_value', 'Estimated value', false),
    field('currency', 'Currency', false),
];

const purposes = [
    {
        value: 'in_kind_offer',
        label: 'In-kind offer',
        description: 'Offer goods or services.',
        fields,
    },
];

const forms = [
    {
        purpose: 'in_kind_offer',
        purposeLabel: 'In-kind offer',
        activeVersion: null,
        versions: [
            {
                id: 'version-1',
                version: 1,
                status: 'draft',
                fields,
                activatedAt: null,
                canActivate: true,
            },
        ],
    },
];

describe('public forms index', () => {
    afterEach(cleanup);

    /*
     * The fields were a two-column grid, so a list whose whole point is the
     * order the fields appear in read 1,2 then 3,4 across the card.
     */
    it('lists the fields once, in form order, in a single sequence', () => {
        render(<PublicFormsIndex purposes={purposes} forms={forms} />);

        const list = screen.getByRole('list');
        expect(list).not.toHaveClass('sm:grid-cols-2');
        expect(
            within(list)
                .getAllByRole('listitem')
                .map((item) => item.textContent?.replace(/^·\s*/, '')),
        ).toEqual([
            'Name',
            'Email address',
            'Category',
            'Estimated value(optional)',
            'Currency(optional)',
        ]);
    });

    /*
     * "required" against every field says nothing when it is the common case,
     * and it was printed five times on a five-field form.
     */
    it('marks only the exception and states the count once', () => {
        render(<PublicFormsIndex purposes={purposes} forms={forms} />);

        expect(screen.queryByText(/· required/)).not.toBeInTheDocument();
        expect(screen.getByText('5 fields, 2 optional')).toBeVisible();
    });
});
