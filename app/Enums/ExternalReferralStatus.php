<?php

namespace App\Enums;

enum ExternalReferralStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Connected = 'connected';
    case NotConnected = 'not_connected';
    case Cancelled = 'cancelled';

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Acknowledged, self::Connected, self::NotConnected, self::Cancelled],
            self::Acknowledged => [self::Connected, self::NotConnected, self::Cancelled],
            default => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
