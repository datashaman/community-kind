<?php

namespace App\Actions\Parties;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\PartyTimelineEventType;
use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class StorePartyAddress
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly ClassifiedDataEncrypter $encrypter,
        private readonly RecordPartyTimelineEvent $recordTimelineEvent,
    ) {}

    /** @param array{label: string, address: string, service_area: string|null, country_code: string} $attributes */
    public function handle(Party $party, array $attributes, User $actor): PartyAddress
    {
        $this->organisationContext->ensureOwns($party->organisation_id);

        return DB::transaction(function () use ($party, $attributes, $actor): PartyAddress {
            $address = new PartyAddress;
            $address->forceFill([
                'id' => $address->newUniqueId(),
                'organisation_id' => $party->organisation_id,
                'party_id' => $party->id,
                'type' => 'address',
                'label' => $attributes['label'],
                'data_key_version' => $this->encrypter->currentVersion(),
                'service_area' => $attributes['service_area'],
                'country_code' => $attributes['country_code'],
            ]);
            $address->encrypted_value = new ClassifiedValue($attributes['address']);
            $address->save();
            $this->recordTimelineEvent->handle(
                $party,
                PartyTimelineEventType::AddressAdded,
                $attributes['label'].' address added',
                $actor,
                'party_address',
                $address->id,
                ['service_area' => $attributes['service_area']],
            );

            return $address;
        });
    }
}
