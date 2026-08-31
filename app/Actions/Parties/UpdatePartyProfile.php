<?php

namespace App\Actions\Parties;

use App\Enums\PartyBusinessRole;
use App\Enums\PartyKind;
use App\Enums\PartyTimelineEventType;
use App\Models\Party;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UpdatePartyProfile
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly SyncPartyContacts $syncPartyContacts,
        private readonly RecordPartyTimelineEvent $recordTimelineEvent,
    ) {}

    /**
     * @param  array{kind: PartyKind, display_name: string, email: string|null, telephone: string|null, program_ids: list<int>, roles: list<PartyBusinessRole>, interests: list<string>}  $attributes
     */
    public function handle(Party $party, array $attributes, User $actor): Party
    {
        $this->organisationContext->ensureOwns($party->organisation_id);

        return DB::transaction(function () use ($party, $attributes, $actor): Party {
            $party->fill([
                'kind' => $attributes['kind'],
                'display_name' => $attributes['display_name'],
            ]);
            $changedFields = array_keys($party->getDirty());
            $party->save();
            $party->programs()->sync($attributes['program_ids']);
            $party->businessRoles()->delete();
            $party->businessRoles()->createMany(collect($attributes['roles'])
                ->map(fn (PartyBusinessRole $role): array => [
                    'organisation_id' => $party->organisation_id,
                    'role' => $role,
                ])->all());
            $party->interests()->delete();
            $party->interests()->createMany(collect($attributes['interests'])
                ->map(fn (string $interest): array => [
                    'organisation_id' => $party->organisation_id,
                    'slug' => Str::slug($interest),
                    'label' => $interest,
                ])->all());
            $this->syncPartyContacts->handle($party, [
                'email' => $attributes['email'],
                'telephone' => $attributes['telephone'],
            ]);
            $this->recordTimelineEvent->handle(
                $party,
                PartyTimelineEventType::ProfileUpdated,
                'Profile updated',
                $actor,
                'party',
                $party->uuid,
                ['changed_fields' => implode(',', $changedFields)],
            );

            return $party->refresh();
        });
    }
}
