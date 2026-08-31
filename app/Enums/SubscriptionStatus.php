<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PendingActivation = 'pending_activation';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Ended = 'ended';
}
