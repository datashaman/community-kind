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
}
