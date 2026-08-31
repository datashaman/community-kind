<?php

namespace App\Enums;

enum TenantAuditEventType: string
{
    case ProgramUpdated = 'program_updated';

    /** @return array<string, string> */
    public function payloadSchema(): array
    {
        return match ($this) {
            self::ProgramUpdated => [
                'program_id' => 'integer',
                'changed_fields' => 'string_list',
            ],
        };
    }
}
