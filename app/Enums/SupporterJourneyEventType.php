<?php

namespace App\Enums;

enum SupporterJourneyEventType: string
{
    case Queued = 'queued';
    case Delivered = 'delivered';
    case Bounced = 'bounced';
    case Retried = 'retried';
    case Unsubscribed = 'unsubscribed';
    case Cancelled = 'cancelled';
    case MeaningfulAction = 'meaningful_action';
}
