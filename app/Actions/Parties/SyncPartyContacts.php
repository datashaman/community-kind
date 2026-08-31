<?php

namespace App\Actions\Parties;

use App\Enums\PartyContactType;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\OrganisationContext;

final class SyncPartyContacts
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly StorePartyContact $storePartyContact,
    ) {}

    /** @param array{email: string|null, telephone: string|null} $contacts */
    public function handle(Party $party, array $contacts): void
    {
        $this->organisationContext->ensureOwns($party->organisation_id);
        $this->sync($party, PartyContactType::Email, $contacts['email']);
        $this->sync($party, PartyContactType::Telephone, $contacts['telephone']);
    }

    private function sync(Party $party, PartyContactType $type, ?string $value): void
    {
        $contact = PartyContactPoint::query()
            ->where('party_id', $party->id)
            ->where('type', $type)
            ->first();

        if ($contact !== null && $value !== null && $contact->encrypted_value->reveal() === $value) {
            return;
        }

        $contact?->delete();

        if ($value !== null) {
            $this->storePartyContact->handle($party, $type, $value);
        }
    }
}
