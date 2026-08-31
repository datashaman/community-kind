<?php

namespace App\Actions\Parties;

use App\Enums\PartyBusinessRole;
use App\Enums\PartyKind;
use App\Enums\PartyTimelineEventType;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreatePartyProfile
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly SyncPartyContacts $syncPartyContacts,
        private readonly RecordPartyTimelineEvent $recordTimelineEvent,
    ) {}

    /**
     * @param  array{kind: PartyKind, display_name: string, email: string|null, telephone: string|null, program_ids: list<int>, roles: list<PartyBusinessRole>, interests: list<string>}  $attributes
     */
    public function handle(Organisation $organisation, array $attributes, User $actor): Party
    {
        $this->organisationContext->ensureOwns($organisation->id);

        return DB::transaction(function () use ($organisation, $attributes, $actor): Party {
            $party = Party::query()->create([
                'organisation_id' => $organisation->id,
                'kind' => $attributes['kind'],
                'display_name' => $attributes['display_name'],
            ]);

            $party->programs()->sync($attributes['program_ids']);
            $party->businessRoles()->createMany(collect($attributes['roles'])
                ->map(fn (PartyBusinessRole $role): array => [
                    'organisation_id' => $organisation->id,
                    'role' => $role,
                ])->all());
            $party->interests()->createMany(collect($attributes['interests'])
                ->map(fn (string $interest): array => [
                    'organisation_id' => $organisation->id,
                    'slug' => Str::slug($interest),
                    'label' => $interest,
                ])->all());
            $this->syncPartyContacts->handle($party, [
                'email' => $attributes['email'],
                'telephone' => $attributes['telephone'],
            ]);
            $this->recordTimelineEvent->handle(
                $party,
                PartyTimelineEventType::ProfileCreated,
                'Profile created',
                $actor,
                'party',
                $party->uuid,
            );

            return $party;
        });
    }
}
