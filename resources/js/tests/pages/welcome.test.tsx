import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import type { ComponentProps } from 'react';
import { describe, expect, it, vi } from 'vitest';
import Welcome from '@/pages/welcome';

type InertiaLinkProps = Omit<ComponentProps<'a'>, 'href'> & {
    href: string | { url: string };
};

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    Link: ({ children, href, ...props }: InertiaLinkProps) => (
        <a {...props} href={typeof href === 'string' ? href : href.url}>
            {children}
        </a>
    ),
    usePage: () => ({
        props: { auth: { user: null }, currentOrganisation: null },
    }),
}));

vi.mock('@/components/source-and-licence-link', () => ({
    default: () => <a href="/source-and-licence">Source and licence</a>,
}));

describe('Welcome', () => {
    it('explains the connected operating model and offers evaluation paths', () => {
        render(<Welcome />);

        expect(
            screen.getByRole('heading', {
                name: 'One shared thread from first contact to visible impact.',
            }),
        ).toBeVisible();
        expect(
            screen.getByRole('heading', {
                name: 'The work makes more sense when the thread stays intact.',
            }),
        ).toBeVisible();
        expect(screen.getByText('Service delivery')).toBeVisible();
        expect(screen.getByText('Community engagement')).toBeVisible();
        expect(screen.getByText('Supporter stewardship')).toBeVisible();
        expect(screen.getByText('Impact evidence')).toBeVisible();
        expect(
            screen.getByRole('link', { name: 'Explore the demo' }),
        ).toHaveAttribute('href', '/demo');
        expect(
            screen.getAllByRole('link', { name: 'Read the documentation' })[0],
        ).toHaveAttribute(
            'href',
            'https://github.com/datashaman/community-kind/tree/main/docs',
        );
        expect(
            screen.getAllByRole('link', { name: 'Staff log in' })[0],
        ).toHaveAttribute('href', '/login');
        expect(screen.getByText(/not yet a production service/i)).toBeVisible();
    });
});
