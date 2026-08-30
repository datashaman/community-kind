<?php

namespace App\Enums;

enum OrganisationLifecycleEventType: string
{
    case StatusChanged = 'status_changed';
    case AccessHoldPlaced = 'access_hold_placed';
    case AccessHoldReleased = 'access_hold_released';
    case OwnershipTransferNominated = 'ownership_transfer_nominated';
    case OwnershipTransferAccepted = 'ownership_transfer_accepted';
    case OwnershipTransferCancelled = 'ownership_transfer_cancelled';
    case SlugChanged = 'slug_changed';
}
