<?php

namespace App\Enums;

enum TenantAuditEventType: string
{
    case ProgramUpdated = 'program_updated';
    case CaseAssigned = 'case_assigned';
    case CaseViewed = 'case_viewed';
    case RestrictedCaseViewed = 'restricted_case_viewed';
    case CaseReclassified = 'case_reclassified';
    case CaseRiskRecorded = 'case_risk_recorded';
    case RestrictedAccessGranted = 'restricted_access_granted';
    case RestrictedAccessRevoked = 'restricted_access_revoked';
    case IdentifiableCaseExported = 'identifiable_case_exported';

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
            self::CaseViewed, self::RestrictedCaseViewed => [
                'case_id' => 'string',
                'classification' => 'string',
            ],
            self::CaseReclassified => [
                'case_id' => 'string',
                'from' => 'string',
                'to' => 'string',
                'reason' => 'string',
            ],
            self::CaseRiskRecorded => [
                'case_id' => 'string',
                'classification' => 'string',
            ],
            self::RestrictedAccessGranted => [
                'case_id' => 'nullable_string',
                'membership_id' => 'integer',
                'program_id' => 'integer',
                'permission' => 'string',
                'reason' => 'string',
            ],
            self::RestrictedAccessRevoked => [
                'grant_id' => 'string',
                'reason' => 'string',
            ],
            self::IdentifiableCaseExported => [
                'program_id' => 'integer',
                'record_count' => 'integer',
            ],
        };
    }
}
