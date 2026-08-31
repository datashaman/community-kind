<?php

namespace App\Enums;

enum SecurityIncidentEntryType: string
{
    case Decision = 'decision';
    case Action = 'action';
    case EvidenceReference = 'evidence_reference';
    case RecoveryGate = 'recovery_gate';
    case CorrectiveAction = 'corrective_action';
    case StatusChange = 'status_change';
}
