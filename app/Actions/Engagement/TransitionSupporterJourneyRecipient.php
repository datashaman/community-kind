<?php

namespace App\Actions\Engagement;

use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\PartyTimelineEventType;
use App\Enums\SupporterJourneyEventType;
use App\Enums\SupporterJourneyRecipientStatus;
use App\Models\SupporterJourneyEvent;
use App\Models\SupporterJourneyRecipient;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class TransitionSupporterJourneyRecipient
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimelineEvent,
        private readonly RecordPartyConsent $recordConsent,
    ) {}

    public function handle(SupporterJourneyRecipient $recipient, SupporterJourneyEventType $type, string $idempotencyKey, ?User $actor = null): SupporterJourneyRecipient
    {
        $this->context->ensureOwns($recipient->organisation_id);

        if (! Str::isUuid($idempotencyKey)) {
            throw new LogicException('A journey transition requires a UUID idempotency key.');
        }

        return DB::transaction(function () use ($actor, $idempotencyKey, $recipient, $type): SupporterJourneyRecipient {
            $existing = SupporterJourneyEvent::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                if ($existing->supporter_journey_recipient_id !== $recipient->id || $existing->type !== $type) {
                    throw new LogicException('The journey event idempotency key conflicts with another transition.');
                }

                return $recipient->fresh();
            }

            $locked = SupporterJourneyRecipient::query()->lockForUpdate()->findOrFail($recipient->id);
            $from = $locked->status;
            $to = $this->destination($from, $type);
            $attemptCount = $locked->attempt_count + ($type === SupporterJourneyEventType::Retried ? 1 : 0);
            $locked->update([
                'status' => $to,
                'attempt_count' => $attemptCount,
                'last_attempted_at' => in_array($type, [SupporterJourneyEventType::Delivered, SupporterJourneyEventType::Bounced, SupporterJourneyEventType::Retried], true) ? now() : $locked->last_attempted_at,
            ]);
            SupporterJourneyEvent::query()->create([
                'organisation_id' => $locked->organisation_id,
                'supporter_journey_recipient_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'type' => $type,
                'from_status' => $from->value,
                'to_status' => $to,
                'metadata' => ['simulation' => true],
                'occurred_at' => now(),
            ]);

            if ($type === SupporterJourneyEventType::Unsubscribed) {
                if ($actor === null) {
                    throw new LogicException('An actor is required to record an unsubscribe.');
                }

                $this->recordConsent->handle($locked->party, [
                    'purpose' => ConsentPurpose::SupporterUpdates,
                    'channel' => ConsentChannel::from($locked->journey->audienceSegment->criteria['channel']),
                    'decision' => ConsentDecision::Suppressed,
                    'wording_version' => 'simulated-unsubscribe-v1',
                    'wording' => 'Supporter requested no further simulated email updates.',
                    'source' => 'local-welcome-journey',
                    'occurred_at' => now()->toAtomString(),
                ], $actor);
            }

            $this->recordTimelineEvent->handle(
                $locked->party,
                PartyTimelineEventType::SupporterJourneyTransitioned,
                'Local supporter journey '.$type->value,
                $actor,
                'supporter_journey_recipient',
                $locked->id,
                ['journey_id' => $locked->supporter_journey_id, 'outcome' => $type->value, 'simulation' => true],
            );

            return $locked->fresh();
        });
    }

    private function destination(SupporterJourneyRecipientStatus $from, SupporterJourneyEventType $type): SupporterJourneyRecipientStatus
    {
        $allowed = match ($from) {
            SupporterJourneyRecipientStatus::Queued => [
                SupporterJourneyEventType::Queued->value => SupporterJourneyRecipientStatus::Queued,
                SupporterJourneyEventType::Delivered->value => SupporterJourneyRecipientStatus::Delivered,
                SupporterJourneyEventType::Bounced->value => SupporterJourneyRecipientStatus::Bounced,
                SupporterJourneyEventType::Cancelled->value => SupporterJourneyRecipientStatus::Cancelled,
            ],
            SupporterJourneyRecipientStatus::Bounced => [SupporterJourneyEventType::Retried->value => SupporterJourneyRecipientStatus::Queued],
            SupporterJourneyRecipientStatus::Delivered => [
                SupporterJourneyEventType::MeaningfulAction->value => SupporterJourneyRecipientStatus::Delivered,
                SupporterJourneyEventType::Unsubscribed->value => SupporterJourneyRecipientStatus::Unsubscribed,
            ],
            default => [],
        };

        return $allowed[$type->value] ?? throw new LogicException("Cannot apply {$type->value} to a {$from->value} journey recipient.");
    }
}
