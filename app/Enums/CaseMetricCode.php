<?php

namespace App\Enums;

enum CaseMetricCode: string
{
    case CaseOpened = 'case_opened';
    case CaseClosed = 'case_closed';
    case ServiceDelivered = 'service_delivered';
    case GoalAchieved = 'goal_achieved';
    case GoalNotAchieved = 'goal_not_achieved';
    case ReferralConnected = 'referral_connected';
    case ReferralNotConnected = 'referral_not_connected';
}
