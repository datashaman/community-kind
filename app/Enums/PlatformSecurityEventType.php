<?php

namespace App\Enums;

enum PlatformSecurityEventType: string
{
    case OtherBrowserSessionsRevoked = 'other_browser_sessions_revoked';
    case PartyContactIndexKeyRebuilt = 'party_contact_index_key_rebuilt';
    case PartyContactDataKeyRotated = 'party_contact_data_key_rotated';
    case IncidentReported = 'incident_reported';
    case IncidentStatusChanged = 'incident_status_changed';
    case UserAccessRevoked = 'user_access_revoked';
    case OrganisationAccessHoldApplied = 'organisation_access_hold_applied';
    case OrganisationAccessHoldReleased = 'organisation_access_hold_released';
    case InstallationControlApplied = 'installation_control_applied';
    case InstallationControlReleased = 'installation_control_released';
    case CredentialRotationCoordinated = 'credential_rotation_coordinated';
    case AuditIntegrityCheckFailed = 'audit_integrity_check_failed';
    case IncidentExerciseCompleted = 'incident_exercise_completed';

    /** @return array<string, string> */
    public function payloadSchema(): array
    {
        return match ($this) {
            self::OtherBrowserSessionsRevoked => ['revoked_count' => 'integer'],
            self::PartyContactIndexKeyRebuilt => [
                'organisation_uuid' => 'string',
                'record_count' => 'integer',
                'current_version' => 'string',
                'previous_version' => 'nullable_string',
            ],
            self::PartyContactDataKeyRotated => [
                'organisation_uuid' => 'string',
                'record_count' => 'integer',
                'from_versions' => 'string_list',
                'to_version' => 'string',
            ],
            self::IncidentReported => [
                'incident_uuid' => 'string',
                'classification' => 'string',
                'severity' => 'string',
                'detection_source' => 'string',
            ],
            self::IncidentStatusChanged => [
                'incident_uuid' => 'string',
                'from_status' => 'string',
                'to_status' => 'string',
            ],
            self::UserAccessRevoked => [
                'incident_uuid' => 'string',
                'user_id' => 'integer',
                'session_count' => 'integer',
                'invitation_count' => 'integer',
                'password_reset_revoked' => 'boolean',
            ],
            self::OrganisationAccessHoldApplied => [
                'incident_uuid' => 'string',
                'organisation_uuid' => 'string',
                'hold_id' => 'string',
                'scope' => 'string',
                'access_level' => 'string',
                'reason_code' => 'string',
            ],
            self::OrganisationAccessHoldReleased => [
                'incident_uuid' => 'string',
                'organisation_uuid' => 'string',
                'hold_id' => 'string',
                'reason_code' => 'string',
            ],
            self::InstallationControlApplied => [
                'incident_uuid' => 'string',
                'control_id' => 'string',
                'capability' => 'string',
                'reason_code' => 'string',
            ],
            self::InstallationControlReleased => [
                'incident_uuid' => 'string',
                'control_id' => 'string',
                'capability' => 'string',
                'reason_code' => 'string',
            ],
            self::CredentialRotationCoordinated => [
                'incident_uuid' => 'string',
                'credential_reference' => 'string',
            ],
            self::AuditIntegrityCheckFailed => [
                'manifest_date' => 'string',
                'reason_code' => 'string',
                'manifest_digest' => 'nullable_string',
            ],
            self::IncidentExerciseCompleted => [
                'exercise_id' => 'string',
                'scenario_count' => 'integer',
                'pack_digest' => 'string',
            ],
        };
    }
}
