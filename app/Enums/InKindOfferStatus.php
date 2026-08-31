<?php

namespace App\Enums;

enum InKindOfferStatus: string
{
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Fulfilled = 'fulfilled';
    case UnableToFulfil = 'unable_to_fulfil';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Offered => [self::Accepted, self::Declined],
            self::Accepted => [self::Fulfilled, self::UnableToFulfil],
            self::Declined, self::Fulfilled, self::UnableToFulfil => [],
        };
    }
}
