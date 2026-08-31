<?php

namespace App\Enums;

enum SupporterRegistrationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function canCancel(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed, self::Waitlisted], true);
    }

    public function label(): string
    {
        return str($this->value)->headline()->toString();
    }
}
