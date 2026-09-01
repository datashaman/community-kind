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
    it('offers public evaluators a direct path to the demo', () => {
        render(<Welcome />);

        expect(
            screen.getByRole('link', { name: 'Explore the demo' }),
        ).toHaveAttribute('href', '/demo');
    });
});
