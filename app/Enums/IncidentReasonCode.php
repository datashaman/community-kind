<?php

namespace App\Enums;

enum IncidentReasonCode: string
{
    case SuspectedCrossTenantAccess = 'suspected_cross_tenant_access';
    case PrivilegedAccountCompromise = 'privileged_account_compromise';
    case KeyDisclosure = 'key_disclosure';
    case MaliciousFileRelease = 'malicious_file_release';
    case AuditIntegrityFailure = 'audit_integrity_failure';
    case SyntheticExercise = 'synthetic_exercise';
}
