<?php

namespace App\Enums;

enum CaseWorkflowSubject: string
{
    case CaseRecord = 'case';
    case Goal = 'goal';
    case Service = 'service';
    case Referral = 'referral';
    case Task = 'task';
    case Appointment = 'appointment';
    case Note = 'note';
}
