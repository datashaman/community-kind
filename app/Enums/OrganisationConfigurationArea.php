<?php

namespace App\Enums;

enum OrganisationConfigurationArea: string
{
    case PublicForm = 'public_form';
    case MessageTemplate = 'message_template';
    case SupporterJourney = 'supporter_journey';
    case IntakeRules = 'intake_rules';
    case Reporting = 'reporting';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
