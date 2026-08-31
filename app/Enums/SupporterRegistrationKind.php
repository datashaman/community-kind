<?php

namespace App\Enums;

enum SupporterRegistrationKind: string
{
    case Volunteer = 'volunteer';
    case Event = 'event';

    public function label(): string
    {
        return match ($this) {
            self::Volunteer => 'Volunteer opportunity',
            self::Event => 'Event',
        };
    }
}
