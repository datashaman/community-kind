<?php

namespace App\Actions\Parties;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Cryptography\ContactBlindIndexer;
use App\Data\Values\ClassifiedValue;
use App\Enums\PartyContactType;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

class StorePartyContact
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly ClassifiedDataEncrypter $encrypter,
        private readonly ContactBlindIndexer $blindIndexer,
    ) {}

    public function handle(
        Party $party,
        PartyContactType $type,
        #[\SensitiveParameter] string $value,
    ): PartyContactPoint {
        $this->organisationContext->ensureOwns($party->organisation_id);
        $organisation = $this->organisationContext->organisation();
        $indexes = $this->blindIndexer->indexesForWrite($organisation->uuid, $type, $value);
        $currentVersion = $this->blindIndexer->currentVersion();
        $previousVersion = $this->blindIndexer->previousVersion();

        return DB::transaction(function () use (
            $party,
            $type,
            $value,
            $indexes,
            $currentVersion,
            $previousVersion,
        ): PartyContactPoint {
            $contactPoint = new PartyContactPoint;
            $contactPoint->id = $contactPoint->newUniqueId();
            $contactPoint->organisation_id = $party->organisation_id;
            $contactPoint->party_id = $party->id;
            $contactPoint->type = $type;
            $contactPoint->encrypted_value = new ClassifiedValue($value);
            $contactPoint->forceFill([
                'data_key_version' => $this->encrypter->currentVersion(),
                'current_index_key_version' => $currentVersion,
                'current_blind_index' => $indexes[$currentVersion],
                'previous_index_key_version' => $previousVersion,
                'previous_blind_index' => $previousVersion === null ? null : $indexes[$previousVersion],
            ]);
            $contactPoint->save();

            return $contactPoint;
        });
    }
}
