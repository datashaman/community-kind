<?php

namespace App\Enums;

enum IntakeStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Waitlisted = 'waitlisted';
    case Accepted = 'accepted';
    case Redirected = 'redirected';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::UnderReview, self::Withdrawn],
            self::UnderReview => [self::Waitlisted, self::Accepted, self::Redirected, self::Declined, self::Withdrawn],
            self::Waitlisted => [self::UnderReview, self::Accepted, self::Redirected, self::Withdrawn],
            self::Accepted, self::Redirected, self::Declined, self::Withdrawn => [],
        };
    }
}
