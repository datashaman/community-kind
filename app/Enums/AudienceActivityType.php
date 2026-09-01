<?php

namespace App\Enums;

enum AudienceActivityType: string
{
    case Any = 'any';
    case Donation = 'donation';
    case Event = 'event';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return str($this->value)->title()->toString();
    }
}
