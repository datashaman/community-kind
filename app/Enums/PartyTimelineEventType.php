<?php

namespace App\Enums;

enum PartyTimelineEventType: string
{
    case ProfileCreated = 'profile_created';
    case ProfileUpdated = 'profile_updated';
    case RoleAssigned = 'role_assigned';
    case RelationshipAdded = 'relationship_added';
    case AddressAdded = 'address_added';
    case InterestAdded = 'interest_added';
    case ConsentRecorded = 'consent_recorded';
    case SafeContactInstructionRecorded = 'safe_contact_instruction_recorded';
    case DonationCreated = 'donation_created';
    case DonationPaymentTransitioned = 'donation_payment_transitioned';
    case DonationRefunded = 'donation_refunded';
    case RecurringMandateTransitioned = 'recurring_mandate_transitioned';
    case SupporterJourneyTransitioned = 'supporter_journey_transitioned';
    case SupporterRegistrationTransitioned = 'supporter_registration_transitioned';
    case PortalAccessChanged = 'portal_access_changed';
}
