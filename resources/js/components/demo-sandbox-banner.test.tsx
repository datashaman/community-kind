import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import type { ComponentProps, ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DemoSandboxBanner } from './demo-sandbox-banner';

let demoSandbox: {
    pairId: string | null;
    expiresAt: string | null;
    persona: {
        role: string;
        organisation: string;
        responsibility: string;
        boundary: string;
        tasks: { label: string; description: string; href: string }[];
    } | null;
} | null;

type InertiaLinkProps = Omit<ComponentProps<'a'>, 'href'> & {
    href: string | { url: string };
};

vi.mock('@inertiajs/react', () => ({
    Form: ({
        children,
    }: {
        children: (state: { processing: boolean }) => ReactNode;
    }) => <form>{children({ processing: false })}</form>,
    Link: ({ children, href, ...props }: InertiaLinkProps) => (
        <a {...props} href={typeof href === 'string' ? href : href.url}>
            {children}
        </a>
    ),
    usePage: () => ({ props: { demoSandbox } }),
}));

describe('DemoSandboxBanner', () => {
    beforeEach(() => {
        demoSandbox = null;
    });

    it('identifies a synthetic session and its disabled capabilities', () => {
        demoSandbox = {
            pairId: 'sandbox-pair',
            expiresAt: '2026-08-31T20:00:00Z',
            persona: {
                role: 'Case worker',
                organisation: 'HarbourKind',
                responsibility: 'Support people through assigned cases.',
                boundary: 'Assigned case records only.',
                tasks: [
                    {
                        label: 'Open assigned service requests',
                        description: 'Follow requests into service delivery.',
                        href: '/harbourkind/intakes',
                    },
                ],
            },
        };

        render(<DemoSandboxBanner />);

        expect(screen.getByRole('status')).toHaveTextContent(
            'Synthetic demo data',
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'Viewing as Case worker at HarbourKind',
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'uploads, invitations, external messages, payments, and domains disabled',
        );
        expect(
            screen.getByRole('button', { name: 'Reset demo' }),
        ).toBeEnabled();
        expect(
            screen.getByRole('link', { name: 'Change perspective' }),
        ).toHaveAttribute('href', '/demo/personas');
        expect(
            screen.getByRole('link', {
                name: /Open assigned service requests/,
            }),
        ).toHaveAttribute('href', '/harbourkind/intakes');
    });

    it('does not label ordinary sessions as demos', () => {
        const { container } = render(<DemoSandboxBanner />);

        expect(container).toBeEmptyDOMElement();
    });
});
