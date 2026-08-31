<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\EventRegistrationStatus;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyTimelineEventType;
use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\CommunityEvent;
use App\Models\EventRegistration;
use App\Models\Organisation;
use App\Models\PartyRole;
use App\Models\SupporterRegistration;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RegisterForCommunityEvent
{
    public function __construct(private readonly OrganisationContext $context, private readonly ResolvePublicPerson $resolvePerson, private readonly RecordPartyConsent $recordConsent, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    /** @param array{name: string, email: string, consent_email: bool} $attributes */
    public function handle(Organisation $organisation, CommunityEvent $event, array $attributes): EventRegistration
    {
        $this->context->ensureOwns($organisation->id);

        return DB::transaction(function () use ($attributes, $event, $organisation): EventRegistration {
            $event = CommunityEvent::query()->lockForUpdate()->findOrFail($event->id);
            if (! $event->acceptsRegistrations()) {
                throw new LogicException('This event is not accepting registrations.');
            }
            $party = $this->resolvePerson->handle($attributes['name'], $attributes['email']);
            if (EventRegistration::query()->where('community_event_id', $event->id)->where('party_id', $party->id)->exists()) {
                throw new LogicException('This person already has an event registration.');
            }
            $confirmed = EventRegistration::query()->where('community_event_id', $event->id)->where('status', EventRegistrationStatus::Confirmed)->count() < $event->capacity;
            $status = $confirmed ? EventRegistrationStatus::Confirmed : EventRegistrationStatus::Waitlisted;
            PartyRole::query()->firstOrCreate(['organisation_id' => $organisation->id, 'party_id' => $party->id, 'role' => PartyBusinessRole::EventAttendee]);
            $supporterRegistration = SupporterRegistration::query()->create(['organisation_id' => $organisation->id, 'party_id' => $party->id, 'kind' => SupporterRegistrationKind::Event, 'title' => $event->title, 'status' => $confirmed ? SupporterRegistrationStatus::Confirmed : SupporterRegistrationStatus::Pending, 'version' => 1, 'starts_at' => $event->starts_at]);
            $registration = EventRegistration::query()->create(['organisation_id' => $organisation->id, 'community_event_id' => $event->id, 'party_id' => $party->id, 'supporter_registration_id' => $supporterRegistration->id, 'status' => $status, 'version' => 1, 'registered_at' => now()]);
            $this->recordConsent->handle($party, ['purpose' => ConsentPurpose::SupporterUpdates, 'channel' => ConsentChannel::Email, 'decision' => $attributes['consent_email'] ? ConsentDecision::Granted : ConsentDecision::Suppressed, 'wording_version' => 'event-registration-v1', 'wording' => 'I choose whether to receive event follow-up and supporter updates by email.', 'source' => 'public_event_registration', 'occurred_at' => now()->toAtomString()], null);
            $this->recordTimeline->handle($party, PartyTimelineEventType::EventRegistrationTransitioned, "Event registration {$status->value}.", subjectType: 'event_registration', subjectId: $registration->id, metadata: ['status' => $status->value]);
            $this->recordAudit->handle($organisation, TenantAuditEventType::EventRegistrationTransitioned, 'event_registration', $registration->id, ['registration_id' => $registration->id, 'from_status' => 'unregistered', 'to_status' => $status->value]);

            return $registration;
        });
    }
}
