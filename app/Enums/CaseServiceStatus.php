<?php

namespace App\Enums;

enum CaseServiceStatus: string
{
    case Planned = 'planned';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NotDelivered = 'not_delivered';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planned => [self::Scheduled, self::Completed, self::Cancelled],
            self::Scheduled => [self::Completed, self::Cancelled, self::NotDelivered],
            default => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
