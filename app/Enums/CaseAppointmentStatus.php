<?php

namespace App\Enums;

enum CaseAppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return $this === self::Scheduled ? [self::Completed, self::Cancelled, self::NoShow] : [];
    }

    public function isTerminal(): bool
    {
        return $this !== self::Scheduled;
    }
}
