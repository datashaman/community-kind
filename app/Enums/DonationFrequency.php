<?php

namespace App\Enums;

enum DonationFrequency: string
{
    case OneOff = 'one_off';
    case Monthly = 'monthly';
}
