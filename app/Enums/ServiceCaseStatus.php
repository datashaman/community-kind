<?php

namespace App\Enums;

enum ServiceCaseStatus: string
{
    case Open = 'open';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::Active, self::OnHold, self::Closed, self::Cancelled],
            self::Active => [self::OnHold, self::Closed],
            self::OnHold => [self::Active, self::Closed],
            self::Closed, self::Cancelled => [],
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }
}
