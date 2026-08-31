<?php

namespace App\Actions\Parties;

use App\Enums\PartyTimelineEventType;
use App\Models\Party;
use App\Models\PartyTimelineEvent;
use App\Models\User;
use App\OrganisationContext;

final class RecordPartyTimelineEvent
{
    public function __construct(private readonly OrganisationContext $organisationContext) {}

    /** @param array<string, bool|int|string|null> $metadata */
    public function handle(
        Party $party,
        PartyTimelineEventType $type,
        string $summary,
        ?User $actor = null,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        array $metadata = [],
    ): PartyTimelineEvent {
        $this->organisationContext->ensureOwns($party->organisation_id);

        return PartyTimelineEvent::query()->create([
            'organisation_id' => $party->organisation_id,
            'party_id' => $party->id,
            'type' => $type,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId === null ? null : (string) $subjectId,
            'summary' => $summary,
            'metadata' => $metadata,
            'occurred_at' => now(),
            'recorded_by_user_id' => $actor?->id,
        ]);
    }
}
