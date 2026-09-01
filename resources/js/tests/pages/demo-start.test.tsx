import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import type { ComponentProps, ReactNode } from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import DemoStart from '@/pages/demo/start';

type InertiaLinkProps = Omit<ComponentProps<'a'>, 'href'> & {
    href: string | { url: string };
};

let formState: {
    processing: boolean;
    errors: Record<string, string>;
};

vi.mock('@inertiajs/react', () => ({
    Form: ({
        children,
    }: {
        children: (state: typeof formState) => ReactNode;
    }) => <form>{children(formState)}</form>,
    Head: () => null,
    Link: ({ children, href, ...props }: InertiaLinkProps) => (
        <a {...props} href={typeof href === 'string' ? href : href.url}>
            {children}
        </a>
    ),
}));

describe('Demo start', () => {
    beforeEach(() => {
        formState = { processing: false, errors: {} };
    });

    it('explains the safe evaluation journey and offers one primary action', () => {
        render(<DemoStart lifetimeHours={24} />);

        expect(
            screen.getByRole('heading', {
                name: 'Follow the work from first contact to community impact.',
            }),
        ).toBeVisible();
        expect(screen.getByLabelText('Demo journey')).toHaveTextContent(
            'Choose a role',
        );
        expect(
            screen.getByRole('button', { name: 'Prepare my demo' }),
        ).toBeEnabled();
        expect(screen.getByText(/lasts up to 24 hours/i)).toBeVisible();
    });

    it('shows progress and a useful provisioning failure', () => {
        formState = {
            processing: true,
            errors: {
                demo: 'The demo could not be prepared. Please try again.',
            },
        };

        render(<DemoStart lifetimeHours={24} />);

        expect(
            screen.getByRole('button', {
                name: 'Preparing your workspace…',
            }),
        ).toBeDisabled();
        expect(
            screen.getByText(
                'The demo could not be prepared. Please try again.',
            ),
        ).toBeVisible();
    });
});
