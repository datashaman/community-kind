<?php

namespace App\Actions\Parties;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\PartyTimelineEventType;
use App\Models\Party;
use App\Models\PartySafeContactInstruction;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class StoreSafeContactInstruction
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly ClassifiedDataEncrypter $encrypter,
        private readonly RecordPartyTimelineEvent $recordTimelineEvent,
    ) {}

    /** @param array{instruction: string, source: string, effective_at: string} $attributes */
    public function handle(Party $party, array $attributes, User $actor): PartySafeContactInstruction
    {
        $this->organisationContext->ensureOwns($party->organisation_id);

        return DB::transaction(function () use ($party, $attributes, $actor): PartySafeContactInstruction {
            $instruction = new PartySafeContactInstruction;
            $instruction->forceFill([
                'id' => $instruction->newUniqueId(),
                'organisation_id' => $party->organisation_id,
                'party_id' => $party->id,
                'type' => 'instruction',
                'data_key_version' => $this->encrypter->currentVersion(),
                'source' => $attributes['source'],
                'effective_at' => $attributes['effective_at'],
                'recorded_by_user_id' => $actor->id,
            ]);
            $instruction->encrypted_value = new ClassifiedValue($attributes['instruction']);
            $instruction->save();
            $this->recordTimelineEvent->handle(
                $party,
                PartyTimelineEventType::SafeContactInstructionRecorded,
                'Safe-contact instruction recorded',
                $actor,
                'party_safe_contact_instruction',
                $instruction->id,
            );

            return $instruction;
        });
    }
}
