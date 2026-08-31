<?php

namespace App\Enums;

enum RecurringMandateStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PaymentFailed = 'payment_failed';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Cancelled],
            self::Active => [self::PaymentFailed, self::Cancelled],
            self::PaymentFailed => [self::Active, self::Cancelled],
            self::Cancelled => [],
        };
    }
}
