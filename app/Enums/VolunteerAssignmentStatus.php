<?php

namespace App\Enums;

enum VolunteerAssignmentStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Attended = 'attended';
    case NoShow = 'no_show';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Confirmed => [self::Cancelled, self::Attended, self::NoShow],
            self::Cancelled, self::Attended, self::NoShow => [],
        };
    }
}
