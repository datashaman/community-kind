<?php

namespace App\Enums;

enum SupporterJourneyRecipientStatus: string
{
    case Queued = 'queued';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Unsubscribed = 'unsubscribed';
    case Cancelled = 'cancelled';
}
