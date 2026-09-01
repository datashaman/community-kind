import { Archive, ArrowDown, ArrowUp, Plus, RotateCcw } from 'lucide-react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type ProgramDefinition = {
    id: number | null;
    key: string | null;
    label: string;
    retired: boolean;
    field_type?: 'text' | 'textarea' | 'boolean' | 'date';
    is_required?: boolean;
};

type Props<T extends ProgramDefinition> = {
    title: string;
    description: string;
    singular: string;
    fieldName: string;
    definitions: T[];
    canUpdate: boolean;
    supportsFieldType?: boolean;
    supportsRequired?: boolean;
    errors: Record<string, string>;
    onChange: (definitions: T[]) => void;
};

export default function ProgramDefinitionList<T extends ProgramDefinition>({
    title,
    description,
    singular,
    fieldName,
    definitions,
    canUpdate,
    supportsFieldType = false,
    supportsRequired = false,
    errors,
    onChange,
}: Props<T>) {
    const update = (index: number, attributes: Partial<T>) => {
        onChange(
            definitions.map((definition, definitionIndex) =>
                definitionIndex === index
                    ? { ...definition, ...attributes }
                    : definition,
            ),
        );
    };

    const move = (index: number, direction: -1 | 1) => {
        const targetIndex = index + direction;

        if (targetIndex < 0 || targetIndex >= definitions.length) return;

        const reordered = [...definitions];
        [reordered[index], reordered[targetIndex]] = [
            reordered[targetIndex],
            reordered[index],
        ];
        onChange(reordered);
    };

    const add = () => {
        onChange([
            ...definitions,
            {
                id: null,
                key: null,
                label: '',
                retired: false,
                ...(supportsFieldType ? { field_type: 'text' as const } : {}),
                ...(supportsRequired ? { is_required: false } : {}),
            } as T,
        ]);
    };

    return (
        <section className="space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 className="font-semibold">{title}</h3>
                    <p className="text-muted-foreground text-sm">
                        {description}
                    </p>
                </div>
                {canUpdate ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={add}
                    >
                        <Plus /> Add {singular.toLowerCase()}
                    </Button>
                ) : null}
            </div>
            <div className="space-y-2">
                {definitions.map((definition, index) => (
                    <div
                        key={definition.id ?? `new-${fieldName}-${index}`}
                        className={`grid gap-3 rounded-xl border p-3 md:grid-cols-[minmax(12rem,1fr)_auto] md:items-start ${
                            definition.retired
                                ? 'bg-muted/35 border-border/50'
                                : 'bg-card'
                        }`}
                    >
                        <div className="grid gap-2">
                            <Input
                                aria-label={`${singular} ${index + 1} label`}
                                value={definition.label}
                                onChange={(event) =>
                                    update(index, {
                                        label: event.target.value,
                                    } as Partial<T>)
                                }
                                placeholder={`${singular} label`}
                                disabled={!canUpdate || definition.retired}
                            />
                            <div className="flex flex-wrap items-center gap-3">
                                {supportsFieldType ? (
                                    <select
                                        aria-label={`${singular} ${index + 1} type`}
                                        className="h-8 rounded-md border bg-transparent px-2 text-sm"
                                        value={definition.field_type}
                                        disabled={
                                            !canUpdate || definition.retired
                                        }
                                        onChange={(event) =>
                                            update(index, {
                                                field_type: event.target
                                                    .value as ProgramDefinition['field_type'],
                                            } as Partial<T>)
                                        }
                                    >
                                        <option value="text">Short text</option>
                                        <option value="textarea">
                                            Long text
                                        </option>
                                        <option value="boolean">
                                            Yes / no
                                        </option>
                                        <option value="date">Date</option>
                                    </select>
                                ) : null}
                                {supportsRequired ? (
                                    <label className="flex items-center gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            checked={
                                                definition.is_required ?? false
                                            }
                                            disabled={
                                                !canUpdate || definition.retired
                                            }
                                            onChange={(event) =>
                                                update(index, {
                                                    is_required:
                                                        event.target.checked,
                                                } as Partial<T>)
                                            }
                                        />
                                        Required
                                    </label>
                                ) : null}
                                {definition.retired ? (
                                    <Badge variant="outline">Retired</Badge>
                                ) : null}
                            </div>
                            <InputError
                                message={errors[`${fieldName}.${index}.label`]}
                            />
                        </div>
                        {canUpdate ? (
                            <div className="flex items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Move ${definition.label || singular} up`}
                                    disabled={index === 0}
                                    onClick={() => move(index, -1)}
                                >
                                    <ArrowUp />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`Move ${definition.label || singular} down`}
                                    disabled={index === definitions.length - 1}
                                    onClick={() => move(index, 1)}
                                >
                                    <ArrowDown />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={`${definition.retired ? 'Restore' : 'Retire'} ${definition.label || singular}`}
                                    onClick={() =>
                                        update(index, {
                                            retired: !definition.retired,
                                        } as Partial<T>)
                                    }
                                >
                                    {definition.retired ? (
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
            <InputError message={errors[fieldName]} />
        </section>
    );
}
