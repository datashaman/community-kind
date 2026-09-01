<?php

namespace App\Actions\Engagement;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\SupporterJourneyEventType;
use App\Enums\SupporterJourneyRecipientStatus;
use App\Enums\SupporterJourneyStatus;
use App\Models\OrganisationConfiguration;
use App\Models\Party;
use App\Models\SupporterJourney;
use App\Models\SupporterJourneyRecipient;
use App\OrganisationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Ramsey\Uuid\Uuid;

class DispatchSupporterJourney
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly EvaluateAudienceSegment $evaluate,
        private readonly TransitionSupporterJourneyRecipient $transition,
    ) {}

    /** @return Collection<int, SupporterJourneyRecipient> */
    public function handle(SupporterJourney $journey): Collection
    {
        $this->context->ensureOwns($journey->organisation_id);

        if (! config('engagement.simulation_only') || ! app()->environment(['local', 'testing'])) {
            throw new LogicException('Supporter journeys are restricted to local simulation.');
        }

        if (! in_array($journey->status, [SupporterJourneyStatus::Approved, SupporterJourneyStatus::Scheduled], true) || $journey->audience_snapshot === null) {
            throw new LogicException('Only an approved journey can be simulated.');
        }
        if ($journey->status === SupporterJourneyStatus::Scheduled && ($journey->scheduled_for === null || $journey->scheduled_for->isFuture())) {
            throw new LogicException('The scheduled journey is not due for dispatch.');
        }

        $eligibleUuids = $this->evaluate->handle($journey->audienceSegment)->pluck('uuid');
        $snapshotUuids = collect($journey->audience_snapshot)->pluck('uuid');
        $parties = Party::query()->withTrashed()->whereIn('uuid', $snapshotUuids)->get()->keyBy('uuid');

        return DB::transaction(function () use ($eligibleUuids, $journey, $parties, $snapshotUuids): Collection {
            return $snapshotUuids->map(function (string $uuid) use ($eligibleUuids, $journey, $parties): SupporterJourneyRecipient {
                /** @var Party $party */
                $party = $parties->get($uuid);
                $recipient = SupporterJourneyRecipient::query()->firstOrCreate(
                    ['supporter_journey_id' => $journey->id, 'party_id' => $party->id],
                    [
                        'organisation_id' => $journey->organisation_id,
                        'status' => SupporterJourneyRecipientStatus::Queued,
                        'variant' => $journey->experiment === null ? null : ((hexdec(substr(hash('sha256', $party->uuid), 0, 2)) % 2 === 0) ? 'A' : 'B'),
                    ],
                );
                $eligible = $eligibleUuids->contains($uuid) && ! $this->frequencyCapped($recipient);
                $type = $eligible ? SupporterJourneyEventType::Queued : SupporterJourneyEventType::Cancelled;

                return $this->transition->handle(
                    $recipient,
                    $type,
                    Uuid::uuid5($journey->id, "dispatch:{$party->uuid}:{$type->value}")->toString(),
                );
            });
        });
    }

    private function frequencyCapped(SupporterJourneyRecipient $recipient): bool
    {
        $configuredDays = OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::SupporterJourney)->where('configuration_key', 'default')->where('status', OrganisationConfigurationStatus::Active)->latest('version')->value('definition->frequency_cap_days');
        $frequencyCapDays = max((int) config('engagement.frequency_cap_days'), (int) ($configuredDays ?? 0));

        return SupporterJourneyRecipient::query()
            ->where('party_id', $recipient->party_id)
            ->where('supporter_journey_id', '!=', $recipient->supporter_journey_id)
            ->where('status', SupporterJourneyRecipientStatus::Delivered)
            ->where('updated_at', '>=', now()->subDays($frequencyCapDays))
            ->exists();
    }
}
