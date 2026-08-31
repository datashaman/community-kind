<?php

namespace App\Enums;

enum VolunteerCredentialStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Expired = 'expired';
}
