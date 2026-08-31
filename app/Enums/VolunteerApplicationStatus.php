<?php

namespace App\Enums;

enum VolunteerApplicationStatus: string
{
    case Submitted = 'submitted';
    case Onboarding = 'onboarding';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Submitted => [self::Onboarding, self::Rejected, self::Withdrawn],
            self::Onboarding => [self::Approved, self::Rejected, self::Withdrawn],
            self::Approved => [self::Withdrawn],
            self::Rejected, self::Withdrawn => [],
        };
    }
}
