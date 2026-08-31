<?php

namespace App\Actions\Parties;

use App\Cryptography\ContactBlindIndexer;
use App\Enums\PartyContactType;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\OrganisationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FindPartiesByContact
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly ContactBlindIndexer $blindIndexer,
    ) {}

    /** @return Collection<int, Party> */
    public function handle(PartyContactType $type, #[\SensitiveParameter] string $value): Collection
    {
        $organisation = $this->organisationContext->organisation();
        $indexes = $this->blindIndexer->indexesForQuery($organisation->uuid, $type, $value);

        $partyIds = PartyContactPoint::query()
            ->where('type', $type)
            ->where(function (Builder $query) use ($indexes): void {
                foreach ($indexes as $version => $blindIndex) {
                    $query->orWhere(function (Builder $query) use ($version, $blindIndex): void {
                        $query->where('current_index_key_version', $version)
                            ->where('current_blind_index', $blindIndex);
                    })->orWhere(function (Builder $query) use ($version, $blindIndex): void {
                        $query->where('previous_index_key_version', $version)
                            ->where('previous_blind_index', $blindIndex);
                    });
                }
            })
            ->pluck('party_id')
            ->unique();

        return Party::query()
            ->whereKey($partyIds)
            ->orderBy('display_name')
            ->orderBy('id')
            ->get();
    }
}
