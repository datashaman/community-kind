<?php

namespace App\Enums;

enum ConsentChannel: string
{
    case NotApplicable = 'not_applicable';
    case Email = 'email';
    case Sms = 'sms';
    case Telephone = 'telephone';
}
