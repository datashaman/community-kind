import { Head, useForm, usePage } from '@inertiajs/react';
import {
    Archive,
    ArrowDown,
    ArrowUp,
    Check,
    Plus,
    RotateCcw,
} from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/programs';
import { update } from '@/routes/organisations/programs';

type ProgramStage = {
    id: number | null;
    key: string | null;
    label: string;
    retired: boolean;
};

type Program = {
    id: number;
    name: string;
    slug: string;
    request_label: string;
    case_label: string;
    stages: ProgramStage[];
    canUpdate: boolean;
};

function ProgramEditor({
    program,
    organisation,
}: {
    program: Program;
    organisation: string;
}) {
    const form = useForm({
        name: program.name,
        slug: program.slug,
        request_label: program.request_label,
        case_label: program.case_label,
        stages: program.stages,
    });
    const errors = form.errors as Record<string, string>;

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

    const save = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.patch(update.url([organisation, program.slug]), {
            preserveScroll: true,
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

    return (
        <>
            <Head title="Program pathways" />
            <div className="space-y-6 p-4">
                <Heading
                    title="Program pathways"
                    description="Use familiar language and a clear service pathway so staff can recognise how work progresses."
                />
                <div className="grid gap-6">
                    {programs.map((program) => (
                        <ProgramEditor
                            key={program.id}
                            program={program}
                            organisation={organisation.slug}
                        />
                    ))}
                </div>
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
