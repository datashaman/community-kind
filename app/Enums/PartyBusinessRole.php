<?php

namespace App\Enums;

enum PartyBusinessRole: string
{
    case Client = 'client';
    case Donor = 'donor';
    case Volunteer = 'volunteer';
    case PartnerContact = 'partner_contact';
    case EventAttendee = 'event_attendee';
    case InKindContributor = 'in_kind_contributor';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Donor => 'Donor',
            self::Volunteer => 'Volunteer',
            self::PartnerContact => 'Partner contact',
            self::EventAttendee => 'Event attendee',
            self::InKindContributor => 'In-kind contributor',
        };
    }
}
