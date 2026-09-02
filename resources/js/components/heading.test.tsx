import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import Heading from '@/components/heading';

/*
 * page-layout.test.ts treats a default-variant <Heading> as a page's h1. That
 * assumption only holds while this component renders one, so assert it here.
 */
describe('Heading', () => {
    it('renders the page title as an h1', () => {
        render(<Heading title="Reporting publication" />);

        expect(
            screen.getByRole('heading', {
                level: 1,
                name: 'Reporting publication',
            }),
        ).toBeInTheDocument();
    });

    it('renders a section title one level below the page title', () => {
        render(<Heading title="Profile information" variant="small" />);

        expect(
            screen.getByRole('heading', {
                level: 2,
                name: 'Profile information',
            }),
        ).toBeInTheDocument();
    });

    it('renders the optional description', () => {
        render(
            <Heading title="Programs" description="Define service programs." />,
        );

        expect(
            screen.getByText('Define service programs.'),
        ).toBeInTheDocument();
    });
});
