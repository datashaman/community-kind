import { describe, expect, it } from 'vitest';

/*
 * Two CSS patterns are ruled out by `.impeccable.md`, both because they read as
 * templated rather than designed. They are easy to reintroduce by habit, so they
 * are checked here rather than left to review.
 */

const sources = {
    ...import.meta.glob('./pages/**/*.tsx', {
        query: '?raw',
        import: 'default',
        eager: true,
    }),
    ...import.meta.glob('./components/**/*.tsx', {
        query: '?raw',
        import: 'default',
        eager: true,
    }),
    ...import.meta.glob('./layouts/**/*.tsx', {
        query: '?raw',
        import: 'default',
        eager: true,
    }),
} as Record<string, string>;

const files = Object.keys(sources).sort();

/*
 * A left or right border wider than 1px is banned as a card, list-item, callout
 * or alert accent. These two are neither: each is load-bearing structure, so
 * they are allowed by name with the reason recorded.
 */
const SIDE_BORDER_ALLOWED: Record<string, string> = {
    './pages/welcome.tsx':
        'the vertical spine of the operating-model timeline, which the node dots are positioned against',
    './components/nav-main.tsx':
        'the active-item indicator in the sidebar, a selection state rather than decoration',
};

// border-l-2, border-r-8, border-l-[3px], and so on. 1px sides are fine.
const SIDE_BORDER = /border-[lr]-(?:[2-9]|\d\d|\[)/;

describe('banned visual patterns', () => {
    it('finds the source files', () => {
        expect(files.length).toBeGreaterThan(0);
    });

    it.each(files)('uses no side-stripe accent: %s', (file) => {
        const matched = SIDE_BORDER.test(sources[file]!);

        if (file in SIDE_BORDER_ALLOWED) {
            expect(
                matched,
                `${file} is allowlisted for ${SIDE_BORDER_ALLOWED[file]}, but no longer uses a side border. Remove it from SIDE_BORDER_ALLOWED.`,
            ).toBe(true);

            return;
        }

        expect(
            matched,
            `${file} uses a left or right border wider than 1px as an accent. Use a full border, a background tint, a leading icon, or nothing.`,
        ).toBe(false);
    });

    it.each(files)('fills no text with a gradient: %s', (file) => {
        expect(
            sources[file]!,
            `${file} fills text from a gradient. Use a solid colour, and weight or size for emphasis.`,
        ).not.toMatch(/background-clip:\s*text|\bbg-clip-text\b/);
    });
});
