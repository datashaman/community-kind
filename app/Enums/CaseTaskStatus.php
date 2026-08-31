<?php

namespace App\Enums;

enum CaseTaskStatus: string
{
    case Open = 'open';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return $this === self::Open ? [self::Completed, self::Cancelled] : [];
    }

    public function isTerminal(): bool
    {
        return $this !== self::Open;
    }
}
