import '@testing-library/jest-dom/vitest';
import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import type { ReactNode } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ProgramsIndex from '@/pages/programs/index';

let formIsDirty = false;

vi.mock('@inertiajs/react', () => ({
    Head: () => null,
    useForm: <T,>(data: T) => ({
        data,
        errors: {},
        isDirty: formIsDirty,
        patch: vi.fn(),
        processing: false,
        recentlySuccessful: false,
        setData: vi.fn(),
        setDefaults: vi.fn(),
    }),
    usePage: () => ({
        props: { currentOrganisation: { slug: 'harbour-kind' } },
    }),
}));

vi.mock('@/components/ui/select', () => ({
    Select: ({
        children,
        onValueChange,
        value,
    }: {
        children: ReactNode;
        onValueChange: (value: string) => void;
        value: string;
    }) => (
        <select
            aria-label="Program"
            value={value}
            onChange={(event) => onValueChange(event.target.value)}
        >
            {children}
        </select>
    ),
    SelectContent: ({ children }: { children: ReactNode }) => children,
    SelectItem: ({
        children,
        value,
    }: {
        children: ReactNode;
        value: string;
    }) => <option value={value}>{children}</option>,
    SelectTrigger: () => null,
    SelectValue: () => null,
}));

vi.mock('@/routes/programs', () => ({
    index: vi.fn(),
}));

vi.mock('@/routes/organisations/programs', () => ({
    update: { url: vi.fn() },
}));

const program = (id: number, name: string) => ({
    id,
    name,
    slug: name.toLowerCase().replaceAll(' ', '-'),
    request_label: 'Request',
    case_label: 'Case',
    case_default_classification: 'confidential' as const,
    stages: [
        {
            id: 1,
            key: 'internal_received_key',
            label: 'Received',
            retired: false,
        },
    ],
    outcome_measures: [
        {
            id: 2,
            key: 'internal_outcome_key',
            label: 'Progress',
            unit: 'score',
            retired: false,
        },
    ],
    taxonomies: [
        {
            id: 3,
            key: 'internal_taxonomy_key',
            label: 'Need',
            retired: false,
            values: [
                {
                    id: 4,
                    key: 'internal_value_key',
                    label: 'Housing',
                    retired: false,
                },
            ],
        },
    ],
    intake_fields: [
        {
            id: 5,
            key: 'internal_field_key',
            label: 'Contact preference',
            field_type: 'text' as const,
            is_required: false,
            retired: false,
        },
    ],
    eligibility_questions: [],
    risk_flags: [],
    canUpdate: true,
});

describe('Program pathways', () => {
    beforeEach(() => {
        formIsDirty = false;
    });

    afterEach(() => {
        cleanup();
        vi.restoreAllMocks();
    });

    it('shows one selected Program editor at a time', () => {
        render(
            <ProgramsIndex
                programs={[
                    program(10, 'Family Support'),
                    program(20, 'Youth Development'),
                ]}
            />,
        );

        expect(screen.getByLabelText('Program name')).toHaveValue(
            'Family Support',
        );
        expect(
            screen.queryByDisplayValue('Youth Development'),
        ).not.toBeInTheDocument();
        expect(screen.queryByText(/internal_.*_key/)).not.toBeInTheDocument();

        fireEvent.change(screen.getByRole('combobox', { name: 'Program' }), {
            target: { value: '20' },
        });

        expect(screen.getByLabelText('Program name')).toHaveValue(
            'Youth Development',
        );
        expect(
            screen.queryByDisplayValue('Family Support'),
        ).not.toBeInTheDocument();
    });

    it('keeps the selected Program when unsaved changes are not discarded', () => {
        formIsDirty = true;
        vi.spyOn(window, 'confirm').mockReturnValue(false);

        render(
            <ProgramsIndex
                programs={[
                    program(10, 'Family Support'),
                    program(20, 'Youth Development'),
                ]}
            />,
        );

        fireEvent.change(screen.getByRole('combobox', { name: 'Program' }), {
            target: { value: '20' },
        });

        expect(window.confirm).toHaveBeenCalledWith(
            'Discard your unsaved changes and open another Program?',
        );
        expect(screen.getByLabelText('Program name')).toHaveValue(
            'Family Support',
        );
    });
});
