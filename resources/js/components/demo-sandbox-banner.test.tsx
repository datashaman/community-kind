import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { DemoSandboxBanner } from './demo-sandbox-banner';

let demoSandbox: {
    pairId: string | null;
    expiresAt: string | null;
} | null;

vi.mock('@inertiajs/react', () => ({
    Form: ({
        children,
    }: {
        children: (state: { processing: boolean }) => ReactNode;
    }) => <form>{children({ processing: false })}</form>,
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
        };

        render(<DemoSandboxBanner />);

        expect(screen.getByRole('status')).toHaveTextContent(
            'Synthetic demo data',
        );
        expect(screen.getByRole('status')).toHaveTextContent(
            'uploads, invitations, external messages, payments, and domains are disabled',
        );
        expect(
            screen.getByRole('button', { name: 'Reset demo' }),
        ).toBeEnabled();
    });

    it('does not label ordinary sessions as demos', () => {
        const { container } = render(<DemoSandboxBanner />);

        expect(container).toBeEmptyDOMElement();
    });
});
