<?php

namespace App\Enums;

enum DonationSimulationScenario: string
{
    case Success = 'success';
    case Decline = 'decline';
    case TimeoutThenSuccess = 'timeout_then_success';
    case PartialRefund = 'partial_refund';
    case RecurringFailure = 'recurring_failure';
}
