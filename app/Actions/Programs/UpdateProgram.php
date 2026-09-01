<?php

namespace App\Actions\Programs;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\TenantAuditEventType;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateProgram
{
    public function __construct(private RecordTenantAuditEvent $recordTenantAuditEvent) {}

    /**
     * @param array{
     *     name: string,
     *     slug: string,
     *     request_label?: string,
     *     case_label?: string,
     *     stages?: list<array{id: int|null, label: string, retired: bool}>,
     *     outcome_measures?: list<array{id: int|null, label: string, unit: string|null, retired: bool}>,
     *     taxonomies?: list<array{id: int|null, label: string, retired: bool, values: list<array{id: int|null, label: string, retired: bool}>}>,
     *     configuration?: array<string, mixed>
     * } $attributes
     */
    public function handle(Program $program, array $attributes, User $actor): Program
    {
        return DB::transaction(function () use ($program, $attributes, $actor): Program {
            $program = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $stages = $attributes['stages'] ?? null;
            $outcomeMeasures = $attributes['outcome_measures'] ?? null;
            $taxonomies = $attributes['taxonomies'] ?? null;
            unset($attributes['stages'], $attributes['outcome_measures'], $attributes['taxonomies']);
            $program->fill($attributes);
            $changedFields = array_keys($program->getDirty());
            $program->save();

            foreach ($stages ?? [] as $position => $stageAttributes) {
                $stage = $stageAttributes['id'] === null
                    ? $program->stages()->make(['key' => $this->uniqueKey($program->stages(), $stageAttributes['label'], 'stage')])
                    : $program->stages()->whereKey($stageAttributes['id'])->firstOrFail();
                $stage->fill([
                    'label' => $stageAttributes['label'],
                    'position' => $position,
                    'retired_at' => $stageAttributes['retired'] ? ($stage->retired_at ?? now()) : null,
                ]);

                if (! $stage->exists || $stage->isDirty()) {
                    $stage->save();
                    $changedFields[] = 'stages';
                }
            }

            foreach ($outcomeMeasures ?? [] as $position => $measureAttributes) {
                $measure = $measureAttributes['id'] === null
                    ? $program->outcomeMeasures()->make(['key' => $this->uniqueKey($program->outcomeMeasures(), $measureAttributes['label'], 'measure')])
                    : $program->outcomeMeasures()->whereKey($measureAttributes['id'])->firstOrFail();
                $measure->fill([
                    'label' => $measureAttributes['label'],
                    'unit' => $measureAttributes['unit'],
                    'position' => $position,
                    'retired_at' => $measureAttributes['retired'] ? ($measure->retired_at ?? now()) : null,
                ]);

                if (! $measure->exists || $measure->isDirty()) {
                    $measure->save();
                    $changedFields[] = 'outcome_measures';
                }
            }

            foreach ($taxonomies ?? [] as $position => $taxonomyAttributes) {
                $taxonomy = $taxonomyAttributes['id'] === null
                    ? $program->taxonomies()->make(['key' => $this->uniqueKey($program->taxonomies(), $taxonomyAttributes['label'], 'taxonomy')])
                    : $program->taxonomies()->whereKey($taxonomyAttributes['id'])->firstOrFail();
                $taxonomy->fill([
                    'label' => $taxonomyAttributes['label'],
                    'position' => $position,
                    'retired_at' => $taxonomyAttributes['retired'] ? ($taxonomy->retired_at ?? now()) : null,
                ]);

                if (! $taxonomy->exists || $taxonomy->isDirty()) {
                    $taxonomy->save();
                    $changedFields[] = 'taxonomies';
                }

                foreach ($taxonomyAttributes['values'] as $valuePosition => $valueAttributes) {
                    $value = $valueAttributes['id'] === null
                        ? $taxonomy->values()->make(['key' => $this->uniqueKey($taxonomy->values(), $valueAttributes['label'], 'value')])
                        : $taxonomy->values()->whereKey($valueAttributes['id'])->firstOrFail();
                    $value->fill([
                        'label' => $valueAttributes['label'],
                        'position' => $valuePosition,
                        'retired_at' => $valueAttributes['retired'] ? ($value->retired_at ?? now()) : null,
                    ]);

                    if (! $value->exists || $value->isDirty()) {
                        $value->save();
                        $changedFields[] = 'taxonomies';
                    }
                }
            }

            $changedFields = array_values(array_unique($changedFields));

            $this->recordTenantAuditEvent->handle(
                $program->organisation,
                TenantAuditEventType::ProgramUpdated,
                'program',
                (string) $program->id,
                [
                    'program_id' => $program->id,
                    'changed_fields' => $changedFields,
                ],
                $actor,
            );

            return $program->refresh()->load(['stages', 'outcomeMeasures', 'taxonomies.values']);
        });
    }

    /**
     * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
     * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  HasMany<TRelatedModel, TDeclaringModel>  $relationship
     */
    private function uniqueKey(HasMany $relationship, string $label, string $fallback): string
    {
        $base = Str::of($label)->ascii()->snake()->limit(56, '')->toString() ?: $fallback;
        $key = $base;
        $suffix = 2;

        while ($relationship->where('key', $key)->exists()) {
            $key = Str::limit($base, 56, '').'_'.$suffix;
            $suffix++;
        }

        return $key;
    }
}
