<?php

namespace App\Actions\Parties;

use App\Enums\PartyTimelineEventType;
use App\Models\Party;
use App\Models\PartyRelationship;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CreatePartyRelationship
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly RecordPartyTimelineEvent $recordTimelineEvent,
    ) {}

    /** @param array{related_party: Party, type: string, started_at: string|null} $attributes */
    public function handle(Party $party, array $attributes, User $actor): PartyRelationship
    {
        $this->organisationContext->ensureOwns($party->organisation_id);
        $this->organisationContext->ensureOwns($attributes['related_party']->organisation_id);

        if ($party->is($attributes['related_party'])) {
            throw new LogicException('A Party cannot have a relationship with itself.');
        }

        return DB::transaction(function () use ($party, $attributes, $actor): PartyRelationship {
            $relationship = PartyRelationship::query()->create([
                'organisation_id' => $party->organisation_id,
                'party_id' => $party->id,
                'related_party_id' => $attributes['related_party']->id,
                'type' => $attributes['type'],
                'started_at' => $attributes['started_at'],
            ]);
            $this->recordTimelineEvent->handle(
                $party,
                PartyTimelineEventType::RelationshipAdded,
                'Relationship added',
                $actor,
                'party_relationship',
                $relationship->id,
                ['type' => $attributes['type']],
            );

            return $relationship;
        });
    }
}
