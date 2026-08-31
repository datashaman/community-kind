<?php

namespace App\Enums;

enum EventRegistrationStatus: string
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
    case Attended = 'attended';
    case NoShow = 'no_show';
    case FollowedUp = 'followed_up';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Waitlisted => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Cancelled, self::Attended, self::NoShow],
            self::Attended, self::NoShow => [self::FollowedUp],
            self::Cancelled, self::FollowedUp => [],
        };
    }
}
