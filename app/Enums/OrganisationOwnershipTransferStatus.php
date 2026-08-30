<?php

namespace App\Enums;

enum OrganisationOwnershipTransferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
