<?php

namespace App\Actions\Engagement;

use App\Actions\Parties\FindPartiesByContact;
use App\Actions\Parties\StorePartyContact;
use App\Enums\PartyContactType;
use App\Enums\PartyKind;
use App\Models\Party;

final class ResolvePublicPerson
{
    public function __construct(private readonly FindPartiesByContact $findParties, private readonly StorePartyContact $storeContact) {}

    public function handle(string $name, string $email): Party
    {
        $matches = $this->findParties->handle(PartyContactType::Email, $email)->filter(fn (Party $party): bool => $party->kind === PartyKind::Person);
        $party = $matches->count() === 1 ? $matches->first() : null;
        if ($party instanceof Party) {
            return $party;
        }

        $party = Party::query()->create(['kind' => PartyKind::Person, 'display_name' => $name]);
        $this->storeContact->handle($party, PartyContactType::Email, $email);

        return $party;
    }
}
