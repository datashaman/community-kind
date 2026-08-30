import '@testing-library/jest-dom/vitest';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import SourceAndLicenceLink from './source-and-licence-link';

describe('SourceAndLicenceLink', () => {
    it('links every interface to the public source and licence notice', () => {
        render(<SourceAndLicenceLink />);

        expect(
            screen.getByRole('link', { name: 'Source and licence' }),
        ).toHaveAttribute('href', '/source-and-licence');
    });
});
