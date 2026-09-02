import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: () => null }));
vi.mock('@/layouts/auth-layout', () => ({ default: () => null }));
vi.mock('@/layouts/settings/layout', () => ({ default: () => null }));

import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { resolvePageLayout } from './page-layout';

describe('resolvePageLayout', () => {
    it('keeps the unauthenticated demo persona chooser out of the staff shell', () => {
        expect(resolvePageLayout('demo/personas')).toBeNull();
    });

    it('keeps the supporter portal out of the staff shell', () => {
        expect(resolvePageLayout('portal/show')).toBeNull();
    });
});

describe('page layout ownership', () => {
    /*
     * Page sources as raw text, keyed by path relative to this file. Read
     * through Vite rather than node:fs so the test needs no Node typings.
     */
    const sources = import.meta.glob('./pages/**/*.tsx', {
        query: '?raw',
        import: 'default',
        eager: true,
    }) as Record<string, string>;

    const files = Object.keys(sources).sort();

    it('finds the page components', () => {
        expect(files.length).toBeGreaterThan(0);
    });

    /*
     * `createInertiaApp` resolves a layout for every page through
     * resolvePageLayout, so a page that also wraps itself in that layout
     * renders the whole shell twice: two sidebars, two headers, two demo
     * banners, and a <main> nested inside the shell's own <main>.
     */
    /*
     * AppContent renders SidebarInset, which is a <main>. A page that opens its
     * own <main> therefore nests one landmark inside another: invalid HTML, and
     * a duplicate landmark for anyone navigating by landmark. Pages the resolver
     * gives no layout (welcome, demo, portal) own their <main> legitimately.
     */
    it.each(files)(
        'does not open a <main> the shell already owns: %s',
        (file) => {
            const name = file.replace('./pages/', '').replace(/\.tsx$/, '');

            if (resolvePageLayout(name) === null) {
                return;
            }

            expect(
                sources[file],
                `${name} opens its own <main>, but its resolved layout already renders one`,
            ).not.toContain('<main');
        },
    );

    it.each(files)('does not re-wrap its resolved layout: %s', (file) => {
        const name = file.replace('./pages/', '').replace(/\.tsx$/, '');

        if (resolvePageLayout(name) === null) {
            return;
        }

        for (const layout of ['AppLayout', 'AuthLayout', 'SettingsLayout']) {
            expect(
                sources[file],
                `${name} renders <${layout}> itself, but resolvePageLayout already provides a layout for it`,
            ).not.toContain(`<${layout}`);
        }
    });
});

describe('page title structure', () => {
    const sources = import.meta.glob('./pages/**/*.tsx', {
        query: '?raw',
        import: 'default',
        eager: true,
    }) as Record<string, string>;

    const files = Object.keys(sources).sort();

    const occurrences = (source: string, needle: string) =>
        source.split(needle).length - 1;

    /*
     * Every page needs exactly one h1, from exactly one source: a literal <h1>,
     * a default-variant <Heading> (the page title), or a layout that supplies
     * one. AuthLayout renders the h1 for auth pages; SettingsLayout renders a
     * default <Heading> above the settings sub-nav. Counting them together
     * catches both a missing h1 and two competing ones.
     */
    it.each(files)('has exactly one source of an h1: %s', (file) => {
        const name = file.replace('./pages/', '').replace(/\.tsx$/, '');
        const source = sources[file]!;
        const layout = resolvePageLayout(name);

        const layoutProvides =
            layout === AuthLayout ||
            (Array.isArray(layout) && layout.includes(SettingsLayout))
                ? 1
                : 0;

        // A <Heading> is the page title unless it is the small section variant.
        const headings =
            occurrences(source, '<Heading') -
            occurrences(source, 'variant="small"');

        const literal = occurrences(source, '<h1');

        expect(
            literal + headings + layoutProvides,
            `${name}: expected one h1 source, found ${literal} literal <h1>, ${headings} page-title <Heading>, ${layoutProvides} from its layout`,
        ).toBe(1);
    });
});
