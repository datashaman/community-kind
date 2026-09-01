import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: () => null }));
vi.mock('@/layouts/auth-layout', () => ({ default: () => null }));
vi.mock('@/layouts/settings/layout', () => ({ default: () => null }));

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
