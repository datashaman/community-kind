<?php

namespace App\Actions\Programs;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\TenantAuditEventType;
use App\Models\Program;
use App\Models\User;
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
     *     configuration?: array<string, mixed>
     * } $attributes
     */
    public function handle(Program $program, array $attributes, User $actor): Program
    {
        return DB::transaction(function () use ($program, $attributes, $actor): Program {
            $program = Program::query()->whereKey($program->getKey())->lockForUpdate()->firstOrFail();
            $stages = $attributes['stages'] ?? null;
            unset($attributes['stages']);
            $program->fill($attributes);
            $changedFields = array_keys($program->getDirty());
            $program->save();

            foreach ($stages ?? [] as $position => $stageAttributes) {
                $stage = $stageAttributes['id'] === null
                    ? $program->stages()->make(['key' => $this->uniqueStageKey($program, $stageAttributes['label'])])
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

            return $program->refresh()->load('stages');
        });
    }

    private function uniqueStageKey(Program $program, string $label): string
    {
        $base = Str::of($label)->ascii()->snake()->limit(56, '')->toString() ?: 'stage';
        $key = $base;
        $suffix = 2;

        while ($program->stages()->where('key', $key)->exists()) {
            $key = Str::limit($base, 56, '').'_'.$suffix;
            $suffix++;
        }

        return $key;
    }
}
