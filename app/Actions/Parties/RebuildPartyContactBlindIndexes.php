<?php

namespace App\Actions\Parties;

use App\Cryptography\ContactBlindIndexer;
use App\Models\Organisation;
use App\Models\PartyContactPoint;
use App\Models\PlatformSecurityEvent;
use App\OrganisationContext;

class RebuildPartyContactBlindIndexes
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly ContactBlindIndexer $blindIndexer,
    ) {}

    public function handle(Organisation $organisation): int
    {
        $this->organisationContext->ensureOwns($organisation->id);
        $currentVersion = $this->blindIndexer->currentVersion();
        $previousVersion = $this->blindIndexer->previousVersion();
        $rebuilt = 0;

        PartyContactPoint::query()->lazyById()->each(function (PartyContactPoint $contactPoint) use (
            $organisation,
            $currentVersion,
            $previousVersion,
            &$rebuilt,
        ): void {
            $indexes = $this->blindIndexer->indexesForWrite(
                $organisation->uuid,
                $contactPoint->type,
                $contactPoint->encrypted_value->reveal(),
            );
            $attributes = [
                'current_index_key_version' => $currentVersion,
                'current_blind_index' => $indexes[$currentVersion],
                'previous_index_key_version' => $previousVersion,
                'previous_blind_index' => $previousVersion === null ? null : $indexes[$previousVersion],
            ];

            $contactPoint->forceFill($attributes);

            if (! $contactPoint->isDirty(array_keys($attributes))) {
                return;
            }

            $contactPoint->save();
            $rebuilt++;
        });

        PlatformSecurityEvent::query()->create([
            'type' => 'party_contact_index_key_rebuilt',
            'metadata' => [
                'organisation_uuid' => $organisation->uuid,
                'record_count' => $rebuilt,
                'current_version' => $currentVersion,
                'previous_version' => $previousVersion,
            ],
            'occurred_at' => now(),
        ]);

        return $rebuilt;
    }
}
