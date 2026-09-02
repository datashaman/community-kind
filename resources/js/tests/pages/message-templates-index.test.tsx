import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen, within } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import MessageTemplatesIndex from '@/pages/message-templates/index';

vi.mock('@inertiajs/react', () => ({
    Form: ({ children }: { children?: ReactNode }) => <div>{children}</div>,
    Head: () => null,
    Link: ({ children, href }: { children?: ReactNode; href?: unknown }) => (
        <a href={typeof href === 'string' ? href : '#'}>{children}</a>
    ),
    useForm: () => ({
        data: {
            template_key: '',
            name: '',
            channel: 'email',
            subject: '',
            body: '',
            journey_kind: 'general',
        },
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

vi.mock('@/routes/message-templates', () => ({
    activate: { form: () => ({}) },
    index: () => '/message-templates',
    retire: { form: () => ({}) },
    store: { url: () => '/message-templates' },
}));

const version = (v: number, status: string, body: string) => ({
    id: `v${v}`,
    version: v,
    status,
    channel: 'email' as const,
    subject: 'Welcome',
    body,
    activatedAt: status === 'active' ? '2026-09-01T00:00:00Z' : null,
    canActivate: status === 'draft' && v === 3,
});

const welcome = {
    key: 'welcome-email',
    name: 'Welcome Email',
    channel: 'email' as const,
    journeyKind: 'general',
    retired: false,
    activeVersion: 2,
    versions: [
        version(3, 'draft', 'Third body'),
        version(2, 'active', 'Second body'),
        version(1, 'superseded', 'First body'),
    ],
};

const journeyKinds = [{ value: 'general', label: 'General' }];

afterEach(cleanup);

describe('message templates index', () => {
    it('names the list for what it contains, not "Version history"', () => {
        render(
            <MessageTemplatesIndex
                templates={[welcome]}
                retiredCount={0}
                showRetired={false}
                journeyKinds={journeyKinds}
            />,
        );

        expect(
            screen.getByRole('heading', { name: 'All templates' }),
        ).toBeInTheDocument();
        expect(screen.queryByText('Version history')).not.toBeInTheDocument();

        /*
         * The page title already says "Message templates", so the section
         * heading must name the list without restating it.
         */
        expect(screen.getAllByText('Message templates')).toHaveLength(1);
    });

    it('groups revisions under one template rather than listing each as a record', () => {
        render(
            <MessageTemplatesIndex
                templates={[welcome]}
                retiredCount={0}
                showRetired={false}
                journeyKinds={journeyKinds}
            />,
        );

        // One heading for the template, not one per revision.
        expect(screen.getAllByText('Welcome Email')).toHaveLength(1);

        const versions = screen.getByText('3 versions');
        expect(versions).toBeInTheDocument();

        const list = versions.closest('details')!;
        expect(within(list).getByText('v1')).toBeInTheDocument();
        expect(within(list).getByText('v2')).toBeInTheDocument();
        expect(within(list).getByText('v3')).toBeInTheDocument();
    });

    it('says which version journeys actually use', () => {
        render(
            <MessageTemplatesIndex
                templates={[welcome]}
                retiredCount={0}
                showRetired={false}
                journeyKinds={journeyKinds}
            />,
        );

        expect(screen.getByText('Journeys use v2.')).toBeInTheDocument();
    });

    it('warns when no version is active', () => {
        render(
            <MessageTemplatesIndex
                templates={[
                    {
                        ...welcome,
                        activeVersion: null,
                        versions: [version(1, 'draft', 'Only body')],
                    },
                ]}
                retiredCount={0}
                showRetired={false}
                journeyKinds={journeyKinds}
            />,
        );

        expect(
            screen.getByText(/No version is active yet/),
        ).toBeInTheDocument();
    });

    it('offers retirement for a live template', () => {
        render(
            <MessageTemplatesIndex
                templates={[welcome]}
                retiredCount={0}
                showRetired={false}
                journeyKinds={journeyKinds}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Retire' }),
        ).toBeInTheDocument();
    });

    it('does not offer retirement twice, and explains reinstatement', () => {
        render(
            <MessageTemplatesIndex
                templates={[{ ...welcome, retired: true, activeVersion: null }]}
                retiredCount={1}
                showRetired
                journeyKinds={journeyKinds}
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Retire' }),
        ).not.toBeInTheDocument();
        expect(screen.getByText('Retired')).toBeInTheDocument();
        expect(
            screen.getByText(
                /Create a new version to put it back into service/,
            ),
        ).toBeInTheDocument();
    });

    it('links to the retired templates when some are hidden', () => {
        render(
            <MessageTemplatesIndex
                templates={[welcome]}
                retiredCount={2}
                showRetired={false}
                journeyKinds={journeyKinds}
            />,
        );

        expect(
            screen.getByRole('link', { name: 'Show 2 retired' }),
        ).toBeInTheDocument();
    });

    it('teaches the empty state rather than just reporting it', () => {
        render(
            <MessageTemplatesIndex
                templates={[]}
                retiredCount={0}
                showRetired={false}
                journeyKinds={journeyKinds}
            />,
        );

        expect(
            screen.getByText(/Create one above and it will appear here/),
        ).toBeInTheDocument();
    });
});
