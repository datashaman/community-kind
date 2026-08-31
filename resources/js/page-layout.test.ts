import { describe, expect, it, vi } from 'vitest';

vi.mock('@/layouts/app-layout', () => ({ default: () => null }));
vi.mock('@/layouts/auth-layout', () => ({ default: () => null }));
vi.mock('@/layouts/settings/layout', () => ({ default: () => null }));

import { resolvePageLayout } from './page-layout';

describe('resolvePageLayout', () => {
    it('keeps the unauthenticated demo persona chooser out of the staff shell', () => {
        expect(resolvePageLayout('demo/personas')).toBeNull();
    });
});
