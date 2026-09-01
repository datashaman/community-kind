<?php

namespace App\Actions\Engagement;

use App\Enums\SupporterJourneyStatus;
use App\Models\SupporterJourney;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class ApproveSupporterJourney
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly EvaluateAudienceSegment $evaluate,
    ) {}

    public function handle(SupporterJourney $journey, User $actor): SupporterJourney
    {
        $this->context->ensureOwns($journey->organisation_id);

        return DB::transaction(function () use ($actor, $journey): SupporterJourney {
            $locked = SupporterJourney::query()->lockForUpdate()->findOrFail($journey->id);

            if ($locked->status !== SupporterJourneyStatus::Draft) {
                return $locked;
            }

            $snapshot = $this->evaluate->handle($locked->audienceSegment)
                ->map(fn (array $supporter): array => [
                    'uuid' => $supporter['uuid'],
                    'displayName' => $supporter['displayName'],
                    'donationCount' => $supporter['donationCount'],
                    'activityFrequency' => $supporter['activityFrequency'],
                    'activityValue' => $supporter['activityValue'],
                ])->values()->all();
            $locked->update([
                'status' => SupporterJourneyStatus::Approved,
                'audience_snapshot' => $snapshot,
                'approval_hash' => hash('sha256', json_encode([
                    'subject' => $locked->subject,
                    'body' => $locked->body,
                    'journey_kind' => $locked->journey_kind->value,
                    'channel' => $locked->channel,
                    'experiment' => $locked->experiment,
                    'audience' => array_column($snapshot, 'uuid'),
                ], JSON_THROW_ON_ERROR)),
                'approved_at' => now(),
                'approved_by_user_id' => $actor->id,
                'version' => $locked->version + 1,
            ]);

            if ($locked->audience_snapshot === null) {
                throw new LogicException('The approved audience snapshot could not be frozen.');
            }

            return $locked->fresh();
        });
    }
}
