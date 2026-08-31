<?php

namespace App\Actions\Demo;

use App\Actions\Parties\StorePartyContact;
use App\Data\Demo\ScenarioCatalog;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyContactType;
use App\Enums\PartyKind;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\Models\PartyInterest;
use App\Models\PartyRole;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Ramsey\Uuid\Uuid;

/**
 * @phpstan-type ProgramDefinition array{name: string, slug: string, configuration: array<string, mixed>}
 * @phpstan-type MemberDefinition array{party_uuid: string, name: string, email: string, telephone: string, owner: bool, role: OrganisationRole|null, program_slugs: list<string>}
 * @phpstan-type PartyDefinition array{uuid: string, kind: PartyKind, name: string, email?: string, telephone?: string, program_slugs?: list<string>, roles?: list<PartyBusinessRole>, interests?: list<string>}
 * @phpstan-type ScenarioDefinition array{uuid: string, name: string, slug: string, timezone: string, currency: string, reporting_at: string, synthetic: true, party_population: array<string, int>, programs: list<ProgramDefinition>, members: list<MemberDefinition>, parties: list<PartyDefinition>}
 */
final class BuildOrganisationScenario
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly StorePartyContact $storePartyContact,
        private readonly BuildRequestToOutcomeScenario $buildRequestToOutcomeScenario,
        private readonly BuildDonorToRetainedSupporterScenario $buildDonorToRetainedSupporterScenario,
    ) {}

    /** @param ScenarioDefinition $scenario */
    public function handle(array $scenario): Organisation
    {
        return DB::transaction(function () use ($scenario): Organisation {
            $organisation = Organisation::withTrashed()->firstOrNew(['uuid' => $scenario['uuid']]);

            if ($organisation->exists && ($organisation->trashed() || $organisation->status !== OrganisationStatus::Active)) {
                throw new LogicException("The {$scenario['name']} demo Organisation is not active and cannot be reseeded safely.");
            }

            $attributes = [
                'uuid' => $scenario['uuid'],
                'name' => $scenario['name'],
                'slug' => $scenario['slug'],
            ];

            if (! $organisation->exists) {
                $attributes += [
                    'status' => OrganisationStatus::Active,
                    'status_changed_at' => $scenario['reporting_at'],
                    'access_version' => 1,
                ];
            }

            $organisation->forceFill($attributes)->save();

            $this->organisationContext->run($organisation, function () use ($organisation, $scenario): void {
                $programs = $this->upsertPrograms($organisation, $scenario['programs']);

                foreach ($scenario['members'] as $member) {
                    $this->upsertMember($organisation, $member, $programs);
                }

                foreach ($scenario['parties'] as $identity) {
                    $party = $this->upsertParty($organisation, $identity);
                    $this->upsertPartyProfile($party, $identity, $programs);
                }

                $this->upsertPartyPopulation($organisation, $scenario);

                if ($scenario['slug'] === 'harbourkind') {
                    $this->buildRequestToOutcomeScenario->handle(
                        $organisation,
                        Program::query()->where('slug', 'housing-support')->firstOrFail(),
                        Party::query()->where('uuid', '12000000-0000-4000-8000-000000000001')->firstOrFail(),
                        User::query()->where('email', 'manager@harbourkind.example.test')->firstOrFail(),
                        Membership::query()->whereHas('user', fn ($query) => $query->where('email', 'caseworker@harbourkind.example.test'))->firstOrFail(),
                    );
                    $this->buildDonorToRetainedSupporterScenario->handle(
                        Party::query()->where('uuid', '12000000-0000-4000-8000-000000000002')->firstOrFail(),
                    );
                }
            });

            return $organisation;
        });
    }

    /**
     * @param  list<ProgramDefinition>  $programDefinitions
     * @return Collection<string, Program>
     */
    private function upsertPrograms(Organisation $organisation, array $programDefinitions): Collection
    {
        return collect($programDefinitions)->mapWithKeys(function (array $definition) use ($organisation): array {
            $program = Program::withTrashed()->firstOrNew([
                'organisation_id' => $organisation->id,
                'slug' => $definition['slug'],
            ]);
            $program->forceFill([
                'organisation_id' => $organisation->id,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'configuration' => $definition['configuration'],
                'deleted_at' => null,
            ])->save();

            return [$program->slug => $program];
        });
    }

    /**
     * @param  array{party_uuid: string, name: string, email: string, telephone: string, owner: bool, role: OrganisationRole|null, program_slugs: list<string>}  $member
     * @param  Collection<string, Program>  $programs
     */
    private function upsertMember(Organisation $organisation, array $member, Collection $programs): void
    {
        $user = User::query()->firstOrNew(['email' => $member['email']]);
        $user->fill([
            'name' => $member['name'],
            'password' => ScenarioCatalog::PASSWORD,
        ])->forceFill(['email_verified_at' => now()])->save();
        $party = $this->upsertParty($organisation, [
            'uuid' => $member['party_uuid'],
            'kind' => PartyKind::Person,
            'name' => $member['name'],
            'email' => $member['email'],
            'telephone' => $member['telephone'],
        ]);
        $membership = Membership::query()->updateOrCreate(
            ['organisation_id' => $organisation->id, 'user_id' => $user->id],
            [
                'person_party_id' => $party->id,
                'is_owner' => $member['owner'],
                'accepted_at' => now(),
                'ended_at' => null,
            ],
        );

        if ($member['role'] !== null) {
            RoleAssignment::query()->updateOrCreate(
                [
                    'organisation_id' => $organisation->id,
                    'membership_id' => $membership->id,
                    'role' => $member['role']->value,
                    'program_id' => null,
                ],
                ['ended_at' => null],
            );
        }

        $membership->programs()->sync($programs->only($member['program_slugs'])->pluck('id')->all());

        if ($user->current_organisation_id === null) {
            $user->update(['current_organisation_id' => $organisation->id]);
        }
    }

    /** @param array{uuid: string, kind: PartyKind, name: string, email?: string, telephone?: string} $identity */
    private function upsertParty(Organisation $organisation, array $identity): Party
    {
        $party = Party::withTrashed()->firstOrNew(['uuid' => $identity['uuid']]);
        $party->forceFill([
            'uuid' => $identity['uuid'],
            'organisation_id' => $organisation->id,
            'kind' => $identity['kind'],
            'display_name' => $identity['name'],
            'deleted_at' => null,
        ])->save();

        $this->upsertContact($party, PartyContactType::Email, $identity['email'] ?? null);
        $this->upsertContact($party, PartyContactType::Telephone, $identity['telephone'] ?? null);

        return $party;
    }

    private function upsertContact(Party $party, PartyContactType $type, ?string $value): void
    {
        if ($value === null) {
            return;
        }

        $contact = PartyContactPoint::query()
            ->where('party_id', $party->id)
            ->where('type', $type)
            ->first();

        if ($contact !== null && $contact->encrypted_value->reveal() === $value) {
            return;
        }

        $contact?->delete();
        $this->storePartyContact->handle($party, $type, $value);
    }

    /**
     * @param  PartyDefinition  $identity
     * @param  Collection<string, Program>  $programs
     */
    private function upsertPartyProfile(Party $party, array $identity, Collection $programs): void
    {
        $party->programs()->sync($programs->only($identity['program_slugs'] ?? [])->pluck('id')->all());

        PartyRole::query()->where('party_id', $party->id)->delete();
        foreach ($identity['roles'] ?? [] as $role) {
            PartyRole::query()->create([
                'organisation_id' => $party->organisation_id,
                'party_id' => $party->id,
                'role' => $role,
            ]);
        }

        PartyInterest::query()->where('party_id', $party->id)->delete();
        foreach ($identity['interests'] ?? [] as $interest) {
            PartyInterest::query()->create([
                'organisation_id' => $party->organisation_id,
                'party_id' => $party->id,
                'slug' => Str::slug($interest),
                'label' => $interest,
            ]);
        }
    }

    /** @param ScenarioDefinition $scenario */
    private function upsertPartyPopulation(Organisation $organisation, array $scenario): void
    {
        $namedKinds = [PartyKind::Person->value => count($scenario['members'])];

        foreach ($scenario['parties'] as $party) {
            $namedKinds[$party['kind']->value] = ($namedKinds[$party['kind']->value] ?? 0) + 1;
        }

        foreach ($scenario['party_population'] as $kind => $targetCount) {
            $generatedCount = $targetCount - ($namedKinds[$kind] ?? 0);
            $rows = [];

            for ($index = 1; $index <= $generatedCount; $index++) {
                $rows[] = [
                    'uuid' => Uuid::uuid5($organisation->uuid, "scenario-party:{$kind}:{$index}")->toString(),
                    'organisation_id' => $organisation->id,
                    'kind' => $kind,
                    'display_name' => sprintf('Synthetic %s %s %04d', $organisation->name, ucfirst($kind), $index),
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ];
            }

            foreach (array_chunk($rows, 500) as $chunk) {
                Party::query()->upsert(
                    $chunk,
                    ['uuid'],
                    ['organisation_id', 'kind', 'display_name', 'updated_at', 'deleted_at'],
                );
            }
        }
    }
}
