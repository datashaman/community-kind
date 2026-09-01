import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Archive,
    ArrowDown,
    ArrowUp,
    Check,
    Plus,
    RotateCcw,
} from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import ProgramDefinitionList, {
    type ProgramDefinition,
} from '@/components/program-definition-list';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/programs';
import { update } from '@/routes/organisations/programs';

type ProgramStage = {
    id: number | null;
    key: string | null;
    label: string;
    retired: boolean;
};

type OutcomeMeasure = {
    id: number | null;
    key: string | null;
    label: string;
    unit: string;
    retired: boolean;
};

type TaxonomyValue = {
    id: number | null;
    key: string | null;
    label: string;
    retired: boolean;
};

type ProgramTaxonomy = {
    id: number | null;
    key: string | null;
    label: string;
    retired: boolean;
    values: TaxonomyValue[];
};

type IntakeFieldDefinition = ProgramDefinition & {
    field_type: 'text' | 'textarea' | 'boolean' | 'date';
    is_required: boolean;
};

type EligibilityQuestionDefinition = ProgramDefinition & {
    is_required: boolean;
};

type Program = {
    id: number;
    name: string;
    slug: string;
    request_label: string;
    case_label: string;
    case_default_classification: 'confidential' | 'highly_restricted';
    stages: ProgramStage[];
    outcome_measures: OutcomeMeasure[];
    taxonomies: ProgramTaxonomy[];
    intake_fields: IntakeFieldDefinition[];
    eligibility_questions: EligibilityQuestionDefinition[];
    risk_flags: ProgramDefinition[];
    canUpdate: boolean;
};

function ProgramEditor({
    program,
    organisation,
    onDirtyChange,
}: {
    program: Program;
    organisation: string;
    onDirtyChange: (isDirty: boolean) => void;
}) {
    const form = useForm({
        name: program.name,
        slug: program.slug,
        request_label: program.request_label,
        case_label: program.case_label,
        case_default_classification: program.case_default_classification,
        stages: program.stages,
        outcome_measures: program.outcome_measures.map((measure) => ({
            ...measure,
            unit: measure.unit ?? '',
        })),
        taxonomies: program.taxonomies,
        intake_fields: program.intake_fields,
        eligibility_questions: program.eligibility_questions,
        risk_flags: program.risk_flags,
    });
    const errors = form.errors as Record<string, string>;

    useEffect(() => {
        onDirtyChange(form.isDirty);
    }, [form.isDirty, onDirtyChange]);

    const updateStage = (
        stageIndex: number,
        attributes: Partial<ProgramStage>,
    ) => {
        form.setData(
            'stages',
            form.data.stages.map((stage, index) =>
                index === stageIndex ? { ...stage, ...attributes } : stage,
            ),
        );
    };

    const moveStage = (stageIndex: number, direction: -1 | 1) => {
        const targetIndex = stageIndex + direction;

        if (targetIndex < 0 || targetIndex >= form.data.stages.length) return;

        const stages = [...form.data.stages];
        [stages[stageIndex], stages[targetIndex]] = [
            stages[targetIndex],
            stages[stageIndex],
        ];
        form.setData('stages', stages);
    };

    const addStage = () => {
        form.setData('stages', [
            ...form.data.stages,
            { id: null, key: null, label: '', retired: false },
        ]);
    };

    const updateMeasure = (
        measureIndex: number,
        attributes: Partial<OutcomeMeasure>,
    ) => {
        form.setData(
            'outcome_measures',
            form.data.outcome_measures.map((measure, index) =>
                index === measureIndex
                    ? { ...measure, ...attributes }
                    : measure,
            ),
        );
    };

    const moveMeasure = (measureIndex: number, direction: -1 | 1) => {
        const targetIndex = measureIndex + direction;

        if (targetIndex < 0 || targetIndex >= form.data.outcome_measures.length)
            return;

        const measures = [...form.data.outcome_measures];
        [measures[measureIndex], measures[targetIndex]] = [
            measures[targetIndex],
            measures[measureIndex],
        ];
        form.setData('outcome_measures', measures);
    };

    const addMeasure = () => {
        form.setData('outcome_measures', [
            ...form.data.outcome_measures,
            { id: null, key: null, label: '', unit: '', retired: false },
        ]);
    };

    const updateTaxonomy = (
        taxonomyIndex: number,
        attributes: Partial<ProgramTaxonomy>,
    ) => {
        form.setData(
            'taxonomies',
            form.data.taxonomies.map((taxonomy, index) =>
                index === taxonomyIndex
                    ? { ...taxonomy, ...attributes }
                    : taxonomy,
            ),
        );
    };

    const moveTaxonomy = (taxonomyIndex: number, direction: -1 | 1) => {
        const targetIndex = taxonomyIndex + direction;

        if (targetIndex < 0 || targetIndex >= form.data.taxonomies.length)
            return;

        const taxonomies = [...form.data.taxonomies];
        [taxonomies[taxonomyIndex], taxonomies[targetIndex]] = [
            taxonomies[targetIndex],
            taxonomies[taxonomyIndex],
        ];
        form.setData('taxonomies', taxonomies);
    };

    const addTaxonomy = () => {
        form.setData('taxonomies', [
            ...form.data.taxonomies,
            { id: null, key: null, label: '', retired: false, values: [] },
        ]);
    };

    const updateTaxonomyValue = (
        taxonomyIndex: number,
        valueIndex: number,
        attributes: Partial<TaxonomyValue>,
    ) => {
        const taxonomy = form.data.taxonomies[taxonomyIndex];
        updateTaxonomy(taxonomyIndex, {
            values: taxonomy.values.map((value, index) =>
                index === valueIndex ? { ...value, ...attributes } : value,
            ),
        });
    };

    const addTaxonomyValue = (taxonomyIndex: number) => {
        const taxonomy = form.data.taxonomies[taxonomyIndex];
        updateTaxonomy(taxonomyIndex, {
            values: [
                ...taxonomy.values,
                { id: null, key: null, label: '', retired: false },
            ],
        });
    };

    const moveTaxonomyValue = (
        taxonomyIndex: number,
        valueIndex: number,
        direction: -1 | 1,
    ) => {
        const taxonomy = form.data.taxonomies[taxonomyIndex];
        const targetIndex = valueIndex + direction;

        if (targetIndex < 0 || targetIndex >= taxonomy.values.length) return;

        const values = [...taxonomy.values];
        [values[valueIndex], values[targetIndex]] = [
            values[targetIndex],
            values[valueIndex],
        ];
        updateTaxonomy(taxonomyIndex, { values });
    };

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(update.url([organisation, program.slug]), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    const activeStageCount = form.data.stages.filter(
        (stage) => !stage.retired,
    ).length;

    return (
        <Card>
            <CardHeader className="gap-1">
                <CardTitle>{program.name}</CardTitle>
                <p className="text-muted-foreground text-sm">
                    Shape the language and pathway staff use when supporting
                    people through this Program.
                </p>
            </CardHeader>
            <CardContent>
                <form className="space-y-8" onSubmit={save}>
                    <section className="grid gap-4 md:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`program-${program.id}-name`}>
                                Program name
                            </Label>
                            <Input
                                id={`program-${program.id}-name`}
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                disabled={!program.canUpdate}
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor={`program-${program.id}-slug`}>
                                Web address
                            </Label>
                            <Input
                                id={`program-${program.id}-slug`}
                                value={form.data.slug}
                                onChange={(event) =>
                                    form.setData('slug', event.target.value)
                                }
                                disabled={!program.canUpdate}
                            />
                            <InputError message={errors.slug} />
                        </div>
                    </section>

                    <section className="space-y-3">
                        <div>
                            <h3 className="font-semibold">
                                Default case protection
                            </h3>
                            <p className="text-muted-foreground text-sm">
                                Choose the starting classification for cases
                                accepted through this Program. Staff can only
                                lower it later with a recorded justification.
                            </p>
                        </div>
                        <div className="grid max-w-xl gap-2">
                            <Label
                                htmlFor={`program-${program.id}-case-classification`}
                            >
                                New cases start as
                            </Label>
                            <select
                                id={`program-${program.id}-case-classification`}
                                className="h-9 rounded-md border bg-transparent px-3"
                                value={form.data.case_default_classification}
                                onChange={(event) =>
                                    form.setData(
                                        'case_default_classification',
                                        event.target.value as
                                            | 'confidential'
                                            | 'highly_restricted',
                                    )
                                }
                                disabled={!program.canUpdate}
                            >
                                <option value="confidential">
                                    Confidential
                                </option>
                                <option value="highly_restricted">
                                    Highly restricted
                                </option>
                            </select>
                            <InputError
                                message={errors.case_default_classification}
                            />
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div>
                            <h3 className="font-semibold">Language</h3>
                            <p className="text-muted-foreground text-sm">
                                Choose the words people in this Program already
                                use. These labels appear throughout staff
                                workflows.
                            </p>
                        </div>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`program-${program.id}-request-label`}
                                >
                                    A new request is called
                                </Label>
                                <Input
                                    id={`program-${program.id}-request-label`}
                                    value={form.data.request_label}
                                    onChange={(event) =>
                                        form.setData(
                                            'request_label',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Support request"
                                    disabled={!program.canUpdate}
                                />
                                <InputError message={errors.request_label} />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor={`program-${program.id}-case-label`}
                                >
                                    Ongoing work is called
                                </Label>
                                <Input
                                    id={`program-${program.id}-case-label`}
                                    value={form.data.case_label}
                                    onChange={(event) =>
                                        form.setData(
                                            'case_label',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Support journey"
                                    disabled={!program.canUpdate}
                                />
                                <InputError message={errors.case_label} />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h3 className="font-semibold">
                                        Service pathway
                                    </h3>
                                    <Badge variant="secondary">
                                        {activeStageCount} active
                                    </Badge>
                                </div>
                                <p className="text-muted-foreground text-sm">
                                    Arrange stages in the order staff expect
                                    work to progress. Retired stages remain in
                                    history.
                                </p>
                            </div>
                            {program.canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addStage}
                                >
                                    <Plus /> Add stage
                                </Button>
                            ) : null}
                        </div>

                        <div className="space-y-2">
                            {form.data.stages.map((stage, stageIndex) => (
                                <div
                                    key={stage.id ?? `new-${stageIndex}`}
                                    className={`grid gap-3 rounded-xl border p-3 md:grid-cols-[2.25rem_1fr_auto] md:items-center ${
                                        stage.retired
                                            ? 'bg-muted/35 border-border/50'
                                            : 'bg-card'
                                    }`}
                                >
                                    <div
                                        className="bg-primary/10 text-primary flex size-9 items-center justify-center rounded-full text-sm font-semibold"
                                        aria-hidden="true"
                                    >
                                        {stageIndex + 1}
                                    </div>
                                    <div className="grid gap-1">
                                        <Input
                                            aria-label={`Stage ${stageIndex + 1} label`}
                                            value={stage.label}
                                            onChange={(event) =>
                                                updateStage(stageIndex, {
                                                    label: event.target.value,
                                                })
                                            }
                                            placeholder="Stage name"
                                            disabled={
                                                !program.canUpdate ||
                                                stage.retired
                                            }
                                        />
                                        <div className="flex flex-wrap items-center gap-2">
                                            {stage.key ? (
                                                <span className="text-muted-foreground font-mono text-xs">
                                                    {stage.key}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground text-xs">
                                                    A stable reference will be
                                                    created when saved.
                                                </span>
                                            )}
                                            {stage.retired ? (
                                                <Badge variant="outline">
                                                    Retired
                                                </Badge>
                                            ) : null}
                                        </div>
                                        <InputError
                                            message={
                                                errors[
                                                    `stages.${stageIndex}.label`
                                                ]
                                            }
                                        />
                                    </div>
                                    {program.canUpdate ? (
                                        <div className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Move ${stage.label || 'stage'} up`}
                                                disabled={stageIndex === 0}
                                                onClick={() =>
                                                    moveStage(stageIndex, -1)
                                                }
                                            >
                                                <ArrowUp />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`Move ${stage.label || 'stage'} down`}
                                                disabled={
                                                    stageIndex ===
                                                    form.data.stages.length - 1
                                                }
                                                onClick={() =>
                                                    moveStage(stageIndex, 1)
                                                }
                                            >
                                                <ArrowDown />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label={
                                                    stage.retired
                                                        ? `Restore ${stage.label}`
                                                        : `Retire ${stage.label || 'stage'}`
                                                }
                                                onClick={() =>
                                                    updateStage(stageIndex, {
                                                        retired: !stage.retired,
                                                    })
                                                }
                                            >
                                                {stage.retired ? (
                                                    <RotateCcw />
                                                ) : (
                                                    <Archive />
                                                )}
                                            </Button>
                                        </div>
                                    ) : null}
                                </div>
                            ))}
                        </div>
                        <InputError message={errors.stages} />
                    </section>

                    <section className="space-y-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="font-semibold">
                                    Outcome measures
                                </h3>
                                <p className="text-muted-foreground text-sm">
                                    Define the numeric results staff record when
                                    closing work. Stable references keep earlier
                                    outcomes meaningful.
                                </p>
                            </div>
                            {program.canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addMeasure}
                                >
                                    <Plus /> Add measure
                                </Button>
                            ) : null}
                        </div>
                        <div className="space-y-2">
                            {form.data.outcome_measures.map(
                                (measure, measureIndex) => (
                                    <div
                                        key={
                                            measure.id ??
                                            `new-measure-${measureIndex}`
                                        }
                                        className={`grid gap-3 rounded-xl border p-3 md:grid-cols-[1fr_10rem_auto] md:items-start ${
                                            measure.retired
                                                ? 'bg-muted/35 border-border/50'
                                                : 'bg-card'
                                        }`}
                                    >
                                        <div className="grid gap-1">
                                            <Input
                                                aria-label={`Outcome measure ${measureIndex + 1} label`}
                                                value={measure.label}
                                                onChange={(event) =>
                                                    updateMeasure(
                                                        measureIndex,
                                                        {
                                                            label: event.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                                placeholder="Housing stability"
                                                disabled={
                                                    !program.canUpdate ||
                                                    measure.retired
                                                }
                                            />
                                            <span className="text-muted-foreground font-mono text-xs">
                                                {measure.key ??
                                                    'Stable reference created on save'}
                                            </span>
                                            <InputError
                                                message={
                                                    errors[
                                                        `outcome_measures.${measureIndex}.label`
                                                    ]
                                                }
                                            />
                                        </div>
                                        <div className="grid gap-1">
                                            <Input
                                                aria-label={`Outcome measure ${measureIndex + 1} unit`}
                                                value={measure.unit}
                                                onChange={(event) =>
                                                    updateMeasure(
                                                        measureIndex,
                                                        {
                                                            unit: event.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                                placeholder="score"
                                                disabled={
                                                    !program.canUpdate ||
                                                    measure.retired
                                                }
                                            />
                                            <span className="text-muted-foreground text-xs">
                                                Unit
                                            </span>
                                        </div>
                                        {program.canUpdate ? (
                                            <div className="flex items-center gap-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Move ${measure.label || 'measure'} up`}
                                                    disabled={
                                                        measureIndex === 0
                                                    }
                                                    onClick={() =>
                                                        moveMeasure(
                                                            measureIndex,
                                                            -1,
                                                        )
                                                    }
                                                >
                                                    <ArrowUp />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Move ${measure.label || 'measure'} down`}
                                                    disabled={
                                                        measureIndex ===
                                                        form.data
                                                            .outcome_measures
                                                            .length -
                                                            1
                                                    }
                                                    onClick={() =>
                                                        moveMeasure(
                                                            measureIndex,
                                                            1,
                                                        )
                                                    }
                                                >
                                                    <ArrowDown />
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={
                                                        measure.retired
                                                            ? `Restore ${measure.label}`
                                                            : `Retire ${measure.label || 'measure'}`
                                                    }
                                                    onClick={() =>
                                                        updateMeasure(
                                                            measureIndex,
                                                            {
                                                                retired:
                                                                    !measure.retired,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {measure.retired ? (
                                                        <RotateCcw />
                                                    ) : (
                                                        <Archive />
                                                    )}
                                                </Button>
                                            </div>
                                        ) : null}
                                    </div>
                                ),
                            )}
                        </div>
                        <InputError message={errors.outcome_measures} />
                    </section>

                    <section className="space-y-4">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 className="font-semibold">Taxonomies</h3>
                                <p className="text-muted-foreground text-sm">
                                    Maintain the shared classifications staff
                                    use, with clear allowed values instead of
                                    free-form JSON.
                                </p>
                            </div>
                            {program.canUpdate ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={addTaxonomy}
                                >
                                    <Plus /> Add taxonomy
                                </Button>
                            ) : null}
                        </div>
                        <div className="space-y-3">
                            {form.data.taxonomies.map(
                                (taxonomy, taxonomyIndex) => (
                                    <div
                                        key={
                                            taxonomy.id ??
                                            `new-taxonomy-${taxonomyIndex}`
                                        }
                                        className={`space-y-3 rounded-xl border p-4 ${
                                            taxonomy.retired
                                                ? 'bg-muted/35 border-border/50'
                                                : 'bg-card'
                                        }`}
                                    >
                                        <div className="flex flex-wrap items-start gap-2">
                                            <div className="min-w-56 flex-1">
                                                <Input
                                                    aria-label={`Taxonomy ${taxonomyIndex + 1} label`}
                                                    value={taxonomy.label}
                                                    onChange={(event) =>
                                                        updateTaxonomy(
                                                            taxonomyIndex,
                                                            {
                                                                label: event
                                                                    .target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Presenting need"
                                                    disabled={
                                                        !program.canUpdate ||
                                                        taxonomy.retired
                                                    }
                                                />
                                                <span className="text-muted-foreground font-mono text-xs">
                                                    {taxonomy.key ??
                                                        'Stable reference created on save'}
                                                </span>
                                                <InputError
                                                    message={
                                                        errors[
                                                            `taxonomies.${taxonomyIndex}.label`
                                                        ]
                                                    }
                                                />
                                            </div>
                                            {program.canUpdate ? (
                                                <div className="flex items-center gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Move ${taxonomy.label || 'taxonomy'} up`}
                                                        disabled={
                                                            taxonomyIndex === 0
                                                        }
                                                        onClick={() =>
                                                            moveTaxonomy(
                                                                taxonomyIndex,
                                                                -1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowUp />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`Move ${taxonomy.label || 'taxonomy'} down`}
                                                        disabled={
                                                            taxonomyIndex ===
                                                            form.data.taxonomies
                                                                .length -
                                                                1
                                                        }
                                                        onClick={() =>
                                                            moveTaxonomy(
                                                                taxonomyIndex,
                                                                1,
                                                            )
                                                        }
                                                    >
                                                        <ArrowDown />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={
                                                            taxonomy.retired
                                                                ? `Restore ${taxonomy.label}`
                                                                : `Retire ${taxonomy.label || 'taxonomy'}`
                                                        }
                                                        onClick={() =>
                                                            updateTaxonomy(
                                                                taxonomyIndex,
                                                                {
                                                                    retired:
                                                                        !taxonomy.retired,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        {taxonomy.retired ? (
                                                            <RotateCcw />
                                                        ) : (
                                                            <Archive />
                                                        )}
                                                    </Button>
                                                </div>
                                            ) : null}
                                        </div>
                                        <div className="space-y-2 border-l pl-4">
                                            <div className="flex items-center justify-between gap-2">
                                                <span className="text-sm font-medium">
                                                    Allowed values
                                                </span>
                                                {program.canUpdate &&
                                                !taxonomy.retired ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            addTaxonomyValue(
                                                                taxonomyIndex,
                                                            )
                                                        }
                                                    >
                                                        <Plus /> Add value
                                                    </Button>
                                                ) : null}
                                            </div>
                                            {taxonomy.values.map(
                                                (value, valueIndex) => (
                                                    <div
                                                        key={
                                                            value.id ??
                                                            `new-value-${valueIndex}`
                                                        }
                                                        className="flex items-start gap-2"
                                                    >
                                                        <div className="min-w-40 flex-1">
                                                            <Input
                                                                aria-label={`${taxonomy.label || 'Taxonomy'} value ${valueIndex + 1}`}
                                                                value={
                                                                    value.label
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    updateTaxonomyValue(
                                                                        taxonomyIndex,
                                                                        valueIndex,
                                                                        {
                                                                            label: event
                                                                                .target
                                                                                .value,
                                                                        },
                                                                    )
                                                                }
                                                                placeholder="Housing"
                                                                disabled={
                                                                    !program.canUpdate ||
                                                                    taxonomy.retired ||
                                                                    value.retired
                                                                }
                                                            />
                                                            <span className="text-muted-foreground font-mono text-xs">
                                                                {value.key ??
                                                                    'Reference created on save'}
                                                            </span>
                                                            <InputError
                                                                message={
                                                                    errors[
                                                                        `taxonomies.${taxonomyIndex}.values.${valueIndex}.label`
                                                                    ]
                                                                }
                                                            />
                                                        </div>
                                                        {program.canUpdate ? (
                                                            <div className="flex items-center gap-1">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Move ${value.label || 'value'} up`}
                                                                    disabled={
                                                                        valueIndex ===
                                                                        0
                                                                    }
                                                                    onClick={() =>
                                                                        moveTaxonomyValue(
                                                                            taxonomyIndex,
                                                                            valueIndex,
                                                                            -1,
                                                                        )
                                                                    }
                                                                >
                                                                    <ArrowUp />
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={`Move ${value.label || 'value'} down`}
                                                                    disabled={
                                                                        valueIndex ===
                                                                        taxonomy
                                                                            .values
                                                                            .length -
                                                                            1
                                                                    }
                                                                    onClick={() =>
                                                                        moveTaxonomyValue(
                                                                            taxonomyIndex,
                                                                            valueIndex,
                                                                            1,
                                                                        )
                                                                    }
                                                                >
                                                                    <ArrowDown />
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    aria-label={
                                                                        value.retired
                                                                            ? `Restore ${value.label}`
                                                                            : `Retire ${value.label || 'value'}`
                                                                    }
                                                                    onClick={() =>
                                                                        updateTaxonomyValue(
                                                                            taxonomyIndex,
                                                                            valueIndex,
                                                                            {
                                                                                retired:
                                                                                    !value.retired,
                                                                            },
                                                                        )
                                                                    }
                                                                >
                                                                    {value.retired ? (
                                                                        <RotateCcw />
                                                                    ) : (
                                                                        <Archive />
                                                                    )}
                                                                </Button>
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                ),
                                            )}
                                            <InputError
                                                message={
                                                    errors[
                                                        `taxonomies.${taxonomyIndex}.values`
                                                    ]
                                                }
                                            />
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                        <InputError message={errors.taxonomies} />
                    </section>

                    <ProgramDefinitionList
                        title="Intake questions"
                        description="Choose the information staff collect when recording a request, including its input type and whether it is required."
                        singular="Intake question"
                        fieldName="intake_fields"
                        definitions={form.data.intake_fields}
                        canUpdate={program.canUpdate}
                        supportsFieldType
                        supportsRequired
                        errors={errors}
                        onChange={(definitions) =>
                            form.setData('intake_fields', definitions)
                        }
                    />

                    <ProgramDefinitionList
                        title="Eligibility questions"
                        description="Define the yes-or-no checks staff complete during triage. Retired questions remain available for earlier requests."
                        singular="Eligibility question"
                        fieldName="eligibility_questions"
                        definitions={form.data.eligibility_questions}
                        canUpdate={program.canUpdate}
                        supportsRequired
                        errors={errors}
                        onChange={(definitions) =>
                            form.setData('eligibility_questions', definitions)
                        }
                    />

                    <ProgramDefinitionList
                        title="Risk flags"
                        description="Maintain the safety and urgency indicators staff can record without asking them to invent labels."
                        singular="Risk flag"
                        fieldName="risk_flags"
                        definitions={form.data.risk_flags}
                        canUpdate={program.canUpdate}
                        errors={errors}
                        onChange={(definitions) =>
                            form.setData('risk_flags', definitions)
                        }
                    />

                    {program.canUpdate ? (
                        <div className="flex items-center gap-3">
                            <Button disabled={form.processing}>
                                {form.processing
                                    ? 'Saving pathway…'
                                    : 'Save Program'}
                            </Button>
                            {form.recentlySuccessful ? (
                                <span className="text-muted-foreground flex items-center gap-1 text-sm">
                                    <Check className="size-4" /> Saved
                                </span>
                            ) : null}
                        </div>
                    ) : null}
                </form>
            </CardContent>
        </Card>
    );
}

export default function ProgramsIndex({ programs }: { programs: Program[] }) {
    const organisation = usePage().props.currentOrganisation!;
    const [selectedProgramId, setSelectedProgramId] = useState(
        programs[0]?.id.toString() ?? '',
    );
    const [hasUnsavedChanges, setHasUnsavedChanges] = useState(false);
    const selectedProgram = programs.find(
        (program) => program.id.toString() === selectedProgramId,
    );
    const selectProgram = (programId: string) => {
        if (
            programId !== selectedProgramId &&
            hasUnsavedChanges &&
            !window.confirm(
                'Discard your unsaved changes and open another Program?',
            )
        ) {
            return;
        }

        setSelectedProgramId(programId);
    };

    return (
        <>
            <Head title="Program pathways" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Program pathways"
                    description="Use familiar language and a clear service pathway so staff can recognise how work progresses."
                />
                {programs.length > 0 ? (
                    <div className="grid max-w-xl gap-2">
                        <Label htmlFor="program-pathway-selector">
                            Program
                        </Label>
                        <Select
                            value={selectedProgramId}
                            onValueChange={selectProgram}
                        >
                            <SelectTrigger
                                id="program-pathway-selector"
                                className="w-full"
                            >
                                <SelectValue placeholder="Choose a Program" />
                            </SelectTrigger>
                            <SelectContent align="start">
                                {programs.map((program) => (
                                    <SelectItem
                                        key={program.id}
                                        value={program.id.toString()}
                                    >
                                        {program.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <p className="text-muted-foreground text-sm">
                            Choose one Program to review or update its pathway.
                        </p>
                    </div>
                ) : null}
                {selectedProgram ? (
                    <ProgramEditor
                        key={selectedProgram.id}
                        program={selectedProgram}
                        organisation={organisation.slug}
                        onDirtyChange={setHasUnsavedChanges}
                    />
                ) : (
                    <Card>
                        <CardContent className="text-muted-foreground py-8 text-sm">
                            No Programs are available for this Organisation.
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

ProgramsIndex.layout = (props: { currentOrganisation: { slug: string } }) => ({
    breadcrumbs: [
        {
            title: 'Program pathways',
            href: index(props.currentOrganisation.slug),
        },
    ],
});
