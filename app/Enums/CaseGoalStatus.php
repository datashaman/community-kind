<?php

namespace App\Enums;

enum CaseGoalStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Achieved = 'achieved';
    case NotAchieved = 'not_achieved';
    case Cancelled = 'cancelled';
    case Withdrawn = 'withdrawn';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Cancelled],
            self::Active => [self::Achieved, self::NotAchieved, self::Cancelled, self::Withdrawn],
            default => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
