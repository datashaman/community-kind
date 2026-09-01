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
    case CaseDocumentUploaded = 'case_document_uploaded';
    case CaseDocumentScanCompleted = 'case_document_scan_completed';
    case CaseDocumentDownloaded = 'case_document_downloaded';
    case CaseDocumentReplaced = 'case_document_replaced';
    case ServiceOperationsExported = 'service_operations_exported';
    case DonationCreated = 'donation_created';
    case DonationPaymentTransitioned = 'donation_payment_transitioned';
    case DonationRefunded = 'donation_refunded';
    case RecurringMandateTransitioned = 'recurring_mandate_transitioned';
    case AudienceSegmentCreated = 'audience_segment_created';
    case ImpactReportExported = 'impact_report_exported';
    case AuditViewAccessed = 'audit_view_accessed';
    case DemoPersonaSelected = 'demo_persona_selected';
    case DemoOrganisationReset = 'demo_organisation_reset';
    case PortalAccessIssued = 'portal_access_issued';
    case PortalAccessVerified = 'portal_access_verified';
    case PortalAccessRevoked = 'portal_access_revoked';
    case SupporterProfileUpdated = 'supporter_profile_updated';
    case SupporterConsentPreferencesUpdated = 'supporter_consent_preferences_updated';
    case SupporterRegistrationCancelled = 'supporter_registration_cancelled';
    case VolunteerOpportunityCreated = 'volunteer_opportunity_created';
    case VolunteerApplicationSubmitted = 'volunteer_application_submitted';
    case VolunteerApplicationTransitioned = 'volunteer_application_transitioned';
    case VolunteerCredentialRecorded = 'volunteer_credential_recorded';
    case VolunteerCredentialExpired = 'volunteer_credential_expired';
    case VolunteerShiftCreated = 'volunteer_shift_created';
    case VolunteerAssignmentTransitioned = 'volunteer_assignment_transitioned';
    case VolunteerHoursRecorded = 'volunteer_hours_recorded';
    case CommunityEventCreated = 'community_event_created';
    case EventRegistrationTransitioned = 'event_registration_transitioned';
    case EventReminderRecorded = 'event_reminder_recorded';
    case InKindOfferTransitioned = 'in_kind_offer_transitioned';
    case PartnerProfileCreated = 'partner_profile_created';
    case PartnerCommitmentRecorded = 'partner_commitment_recorded';
    case OrganisationConfigurationCreated = 'organisation_configuration_created';
    case OrganisationConfigurationActivated = 'organisation_configuration_activated';
    case OrganisationConfigurationRetired = 'organisation_configuration_retired';
    case ImpactSnapshotPublished = 'impact_snapshot_published';
    case SupporterJourneyTransitioned = 'supporter_journey_transitioned';

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
            self::CaseDocumentUploaded, self::CaseDocumentReplaced => [
                'case_id' => 'string',
                'document_id' => 'string',
                'generation' => 'integer',
                'classification' => 'string',
            ],
            self::CaseDocumentScanCompleted => [
                'document_id' => 'string',
                'generation' => 'integer',
                'outcome' => 'string',
            ],
            self::CaseDocumentDownloaded => [
                'case_id' => 'string',
                'document_id' => 'string',
                'generation' => 'integer',
                'classification' => 'string',
            ],
            self::ServiceOperationsExported => [
                'program_id' => 'nullable_integer',
                'record_count' => 'integer',
            ],
            self::DonationCreated => [
                'donation_id' => 'string',
                'frequency' => 'string',
            ],
            self::DonationPaymentTransitioned => [
                'donation_id' => 'string',
                'payment_id' => 'string',
                'from_status' => 'string',
                'to_status' => 'string',
            ],
            self::DonationRefunded => [
                'donation_id' => 'string',
                'payment_id' => 'string',
                'refund_id' => 'string',
            ],
            self::RecurringMandateTransitioned => [
                'donation_id' => 'string',
                'mandate_id' => 'string',
                'from_status' => 'string',
                'to_status' => 'string',
            ],
            self::AudienceSegmentCreated => [
                'segment_id' => 'string',
                'name' => 'string',
            ],
            self::ImpactReportExported => [
                'registry_version' => 'string',
                'filters_hash' => 'string',
                'metric_count' => 'integer',
                'format' => 'string',
            ],
            self::AuditViewAccessed => [
                'record_count' => 'integer',
                'scope' => 'string',
                'role' => 'string',
            ],
            self::DemoPersonaSelected => [
                'membership_id' => 'integer',
                'role' => 'string',
                'generation' => 'integer',
            ],
            self::DemoOrganisationReset => [
                'generation' => 'integer',
                'template' => 'string',
            ],
            self::PortalAccessIssued => [
                'grant_id' => 'string',
                'party_uuid' => 'string',
                'user_id' => 'integer',
                'expires_at' => 'string',
            ],
            self::PortalAccessVerified, self::PortalAccessRevoked => [
                'grant_id' => 'string',
                'party_uuid' => 'string',
                'user_id' => 'integer',
            ],
            self::SupporterProfileUpdated => [
                'party_uuid' => 'string',
                'changed_fields' => 'string_list',
            ],
            self::SupporterConsentPreferencesUpdated => [
                'party_uuid' => 'string',
                'channels' => 'string_list',
            ],
            self::SupporterRegistrationCancelled => [
                'registration_id' => 'string',
                'party_uuid' => 'string',
                'kind' => 'string',
            ],
            self::VolunteerOpportunityCreated => ['opportunity_id' => 'string', 'capacity' => 'integer'],
            self::VolunteerApplicationSubmitted => ['application_id' => 'string', 'opportunity_id' => 'string', 'party_uuid' => 'string'],
            self::VolunteerApplicationTransitioned => ['application_id' => 'string', 'from_status' => 'string', 'to_status' => 'string'],
            self::VolunteerCredentialRecorded => ['credential_id' => 'string', 'application_id' => 'string', 'status' => 'string'],
            self::VolunteerCredentialExpired => ['credential_id' => 'string', 'application_id' => 'string'],
            self::VolunteerShiftCreated => ['shift_id' => 'string', 'opportunity_id' => 'string', 'capacity' => 'integer'],
            self::VolunteerAssignmentTransitioned => ['assignment_id' => 'string', 'from_status' => 'string', 'to_status' => 'string'],
            self::VolunteerHoursRecorded => ['hours_id' => 'string', 'assignment_id' => 'string', 'minutes' => 'integer'],
            self::CommunityEventCreated => ['event_id' => 'string', 'capacity' => 'integer'],
            self::EventRegistrationTransitioned => ['registration_id' => 'string', 'from_status' => 'string', 'to_status' => 'string'],
            self::EventReminderRecorded => ['registration_id' => 'string'],
            self::InKindOfferTransitioned => ['offer_id' => 'string', 'from_status' => 'string', 'to_status' => 'string'],
            self::PartnerProfileCreated => ['partner_profile_id' => 'string', 'party_uuid' => 'string'],
            self::PartnerCommitmentRecorded => ['commitment_id' => 'string', 'partner_profile_id' => 'string', 'status' => 'string'],
            self::OrganisationConfigurationCreated => ['configuration_id' => 'string', 'area' => 'string', 'configuration_key' => 'string', 'version' => 'integer'],
            self::OrganisationConfigurationActivated => ['configuration_id' => 'string', 'area' => 'string', 'configuration_key' => 'string', 'version' => 'integer'],
            self::OrganisationConfigurationRetired => ['configuration_id' => 'string', 'area' => 'string', 'configuration_key' => 'string', 'version' => 'integer'],
            self::ImpactSnapshotPublished => ['snapshot_id' => 'string', 'audience' => 'string', 'registry_version' => 'string', 'metric_count' => 'integer'],
            self::SupporterJourneyTransitioned => ['journey_id' => 'string', 'from_status' => 'string', 'to_status' => 'string', 'scheduled_for' => 'nullable_string'],
        };
    }
}
