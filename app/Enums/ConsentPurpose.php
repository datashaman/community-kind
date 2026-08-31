<?php

namespace App\Enums;

enum ConsentPurpose: string
{
    case Service = 'service';
    case Referral = 'referral';
    case SafeContact = 'safe_contact';
}
