<?php

namespace App\Enums;

enum SupporterJourneyStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Paused = 'paused';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Approved],
            self::Approved => [self::Scheduled, self::Paused],
            self::Scheduled => [self::Paused, self::Approved],
            self::Paused => [self::Approved, self::Scheduled],
        };
    }
}
