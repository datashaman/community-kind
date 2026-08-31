<?php

namespace App\Enums;

enum DonationPaymentStatus: string
{
    case Created = 'created';
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Created => [self::Pending, self::Cancelled],
            self::Pending => [self::Succeeded, self::Failed, self::Cancelled],
            self::Succeeded => [self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::PartiallyRefunded, self::Refunded],
            self::Failed, self::Cancelled, self::Refunded => [],
        };
    }
}
