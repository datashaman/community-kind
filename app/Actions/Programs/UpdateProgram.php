<?php

namespace App\Actions\Programs;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\TenantAuditEventType;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateProgram
{
    public function __construct(private RecordTenantAuditEvent $recordTenantAuditEvent) {}

    /** @param array{name: string, slug: string, configuration?: array<string, mixed>} $attributes */
    public function handle(Program $program, array $attributes, User $actor): Program
    {
        return DB::transaction(function () use ($program, $attributes, $actor): Program {
            $program->fill($attributes);
            $changedFields = array_keys($program->getDirty());
            $program->save();

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

            return $program;
        });
    }
}
