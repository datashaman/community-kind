<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\EventRegistrationStatus;
use App\Enums\PartyTimelineEventType;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Models\CommunityEvent;
use App\Models\EventRegistration;
use App\Models\PartyConsent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TransitionEventRegistration
{
    public function __construct(private readonly OrganisationContext $context, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    public function handle(EventRegistration $registration, EventRegistrationStatus $to, User $actor): EventRegistration
    {
        $this->context->ensureOwns($registration->organisation_id);

        return DB::transaction(function () use ($actor, $registration, $to): EventRegistration {
            $event = CommunityEvent::query()->lockForUpdate()->findOrFail($registration->community_event_id);
            $locked = EventRegistration::query()->with(['party', 'registration'])->lockForUpdate()->findOrFail($registration->id);
            $locked->setRelation('event', $event);
            $from = $locked->status;
            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition event registration from {$from->value} to {$to->value}.");
            }
            if ($to === EventRegistrationStatus::Confirmed && EventRegistration::query()->where('community_event_id', $locked->community_event_id)->where('status', EventRegistrationStatus::Confirmed)->count() >= $locked->event->capacity) {
                throw new LogicException('The event has no confirmed capacity available.');
            }
            if ($to === EventRegistrationStatus::FollowedUp) {
                $latest = PartyConsent::query()->where('party_id', $locked->party_id)->where('purpose', ConsentPurpose::SupporterUpdates)->where('channel', ConsentChannel::Email)->latest('occurred_at')->latest('id')->first();
                if ($latest?->decision !== ConsentDecision::Granted) {
                    throw new LogicException('Event follow-up is suppressed by consent.');
                }
            }
            $locked->update(['status' => $to, 'version' => $locked->version + 1, 'attended_at' => $to === EventRegistrationStatus::Attended ? now() : $locked->attended_at, 'followed_up_at' => $to === EventRegistrationStatus::FollowedUp ? now() : $locked->followed_up_at, 'transitioned_by_user_id' => $actor->id]);
            $supporterStatus = match ($to) {
                EventRegistrationStatus::Confirmed, EventRegistrationStatus::Attended, EventRegistrationStatus::NoShow, EventRegistrationStatus::FollowedUp => SupporterRegistrationStatus::Confirmed,
                EventRegistrationStatus::Waitlisted => SupporterRegistrationStatus::Pending,
                EventRegistrationStatus::Cancelled => SupporterRegistrationStatus::Cancelled,
            };
            if ($locked->registration->status !== $supporterStatus) {
                $locked->registration->update(['status' => $supporterStatus, 'version' => $locked->registration->version + 1, 'cancelled_at' => $to === EventRegistrationStatus::Cancelled ? now() : null]);
            }
            $this->recordTimeline->handle($locked->party, PartyTimelineEventType::EventRegistrationTransitioned, "Event registration changed from {$from->value} to {$to->value}.", $actor, 'event_registration', $locked->id, ['status' => $to->value]);
            $this->recordAudit->handle($locked->event->organisation, TenantAuditEventType::EventRegistrationTransitioned, 'event_registration', $locked->id, ['registration_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value], $actor);

            return $locked->refresh();
        });
    }

    public function remind(EventRegistration $registration, User $actor): EventRegistration
    {
        $this->context->ensureOwns($registration->organisation_id);

        return DB::transaction(function () use ($actor, $registration): EventRegistration {
            $locked = EventRegistration::query()->with(['party', 'event.organisation'])->lockForUpdate()->findOrFail($registration->id);
            if ($locked->status !== EventRegistrationStatus::Confirmed || $locked->reminded_at !== null) {
                throw new LogicException('Only an unreminded confirmed registration can receive a reminder.');
            }
            $locked->update(['reminded_at' => now(), 'transitioned_by_user_id' => $actor->id]);
            $this->recordTimeline->handle($locked->party, PartyTimelineEventType::EventReminderRecorded, 'Event reminder recorded.', $actor, 'event_registration', $locked->id);
            $this->recordAudit->handle($locked->event->organisation, TenantAuditEventType::EventReminderRecorded, 'event_registration', $locked->id, ['registration_id' => $locked->id], $actor);

            return $locked->refresh();
        });
    }
}
