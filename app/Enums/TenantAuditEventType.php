<?php

namespace App\Enums;

enum TenantAuditEventType: string
{
    case ProgramUpdated = 'program_updated';
    case CaseAssigned = 'case_assigned';

    /** @return array<string, string> */
    public function payloadSchema(): array
    {
        return match ($this) {
            self::ProgramUpdated => [
                'program_id' => 'integer',
                'changed_fields' => 'string_list',
            ],
            self::CaseAssigned => [
                'case_id' => 'string',
                'membership_id' => 'integer',
            ],
        };
    }
}
