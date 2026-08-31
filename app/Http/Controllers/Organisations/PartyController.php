<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Parties\CreatePartyProfile;
use App\Actions\Parties\UpdatePartyProfile;
use App\Enums\ConsentPurpose;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyContactType;
use App\Enums\PartyKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StorePartyProfileRequest;
use App\Http\Requests\Organisations\UpdatePartyProfileRequest;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\Models\PartyRole;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function index(Request $request, Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [Party::class, $currentOrganisation]);
        $request->validate(['query' => ['nullable', 'string', 'max:100']]);
        $user = $request->user();
        $canSearchNames = ! $user->hasOrganisationRole($currentOrganisation, OrganisationRole::OrganisationAdministrator);
        $canCreate = Gate::allows('create', [Party::class, $currentOrganisation]);
        $parties = $this->visibleParties($request->user(), $currentOrganisation)
            ->with(['businessRoles', 'programs:id,name,slug'])
            ->when($canSearchNames && $request->string('query')->isNotEmpty(), fn (Builder $query) => $query
                ->where('display_name', 'like', '%'.$request->string('query')->toString().'%'))
            ->orderBy('display_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Party $party) use ($user): array {
                $supporterSafe = Gate::allows('supporterSafe', $party);
                $administrativeMetadata = $user->hasOrganisationRole($party->organisation, OrganisationRole::OrganisationAdministrator);

                return [
                    'uuid' => $party->uuid,
                    'kind' => $party->kind->value,
                    'displayName' => $administrativeMetadata ? 'Administrative Party record' : $party->display_name,
                    'roles' => $administrativeMetadata
                        ? []
                        : $party->businessRoles
                            ->reject(fn (PartyRole $role): bool => $supporterSafe && $role->role === PartyBusinessRole::Client)
                            ->map(fn (PartyRole $role): string => $role->role->label())->values(),
                    'programs' => $supporterSafe || $administrativeMetadata ? [] : $party->programs->pluck('name')->values(),
                ];
            });

        return Inertia::render('parties/index', [
            'parties' => $parties,
            'query' => $request->string('query')->toString(),
            'canCreate' => $canCreate,
            ...($canCreate ? $this->formOptions($currentOrganisation, $user) : $this->emptyFormOptions()),
        ]);
    }

    public function store(
        StorePartyProfileRequest $request,
        Organisation $currentOrganisation,
        CreatePartyProfile $createPartyProfile,
    ): RedirectResponse {
        Gate::authorize('create', [Party::class, $currentOrganisation]);
        $attributes = $this->profileAttributes($request);
        $this->ensureProgramScope($request->user(), $currentOrganisation, $attributes['program_ids']);
        $party = $createPartyProfile->handle(
            $currentOrganisation,
            $attributes,
            $request->user(),
        );

        return to_route('parties.show', [$currentOrganisation, $party]);
    }

    public function show(Request $request, Organisation $currentOrganisation, string $party): Response
    {
        $party = $this->findParty($party);
        Gate::authorize('view', $party);
        $supporterSafe = Gate::allows('supporterSafe', $party);
        $administrativeMetadata = $request->user()->hasOrganisationRole($party->organisation, OrganisationRole::OrganisationAdministrator);
        $serviceProjection = ! $supporterSafe && ! $administrativeMetadata;
        $party->load([
            'addresses',
            'businessRoles',
            'consents' => fn ($query) => $query->latest('occurred_at')->latest('id'),
            'contactPoints',
            'interests',
            'programs:id,name,slug',
            'relationships.relatedParty',
            'timelineEvents' => fn ($query) => $query->latest('occurred_at')->latest('id')->limit(100),
        ]);
        $canManageSafeContact = Gate::allows('manageSafeContact', $party);
        $canManagePortalAccess = Gate::allows('managePortalAccess', $party);
        $canUpdate = Gate::allows('update', $party);

        if ($canManageSafeContact) {
            $party->load(['safeContactInstructions' => fn ($query) => $query->latest('effective_at')]);
        }

        return Inertia::render('parties/show', [
            'party' => [
                'uuid' => $party->uuid,
                'kind' => $party->kind->value,
                'displayName' => $administrativeMetadata ? 'Administrative Party record' : $party->display_name,
                'email' => $administrativeMetadata ? null : $this->contactValue($party, PartyContactType::Email),
                'telephone' => $administrativeMetadata ? null : $this->contactValue($party, PartyContactType::Telephone),
                'programIds' => $serviceProjection ? $party->programs->modelKeys() : [],
                'roles' => $administrativeMetadata ? [] : $party->businessRoles->pluck('role')
                    ->reject(fn (PartyBusinessRole $role): bool => $supporterSafe && $role === PartyBusinessRole::Client)
                    ->map->value->values(),
                'interests' => $serviceProjection ? $party->interests->pluck('label')->values() : [],
                'addresses' => $serviceProjection ? $party->addresses->map(fn ($address): array => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'address' => $address->encrypted_value->reveal(),
                    'serviceArea' => $address->service_area,
                    'countryCode' => $address->country_code,
                ])->values() : [],
                'relationships' => $serviceProjection ? $party->relationships->map(fn ($relationship): array => [
                    'id' => $relationship->id,
                    'type' => $relationship->type,
                    'relatedParty' => [
                        'uuid' => $relationship->relatedParty->uuid,
                        'displayName' => $relationship->relatedParty->display_name,
                    ],
                    'startedAt' => $relationship->started_at?->toAtomString(),
                    'endedAt' => $relationship->ended_at?->toAtomString(),
                ])->values() : [],
                'consents' => ($serviceProjection || $supporterSafe) ? $party->consents
                    ->when($supporterSafe, fn ($consents) => $consents->where('purpose', ConsentPurpose::SupporterUpdates))
                    ->map(fn ($consent): array => [
                        'id' => $consent->id,
                        'purpose' => $consent->purpose->value,
                        'channel' => $consent->channel->value,
                        'decision' => $consent->decision->value,
                        'wordingVersion' => $consent->wording_version,
                        'wording' => $consent->wording,
                        'source' => $consent->source,
                        'occurredAt' => $consent->occurred_at->toAtomString(),
                        'supersedesId' => $consent->supersedes_id,
                    ])->values() : [],
                'safeContactInstructions' => $canManageSafeContact
                    ? $party->safeContactInstructions->map(fn ($instruction): array => [
                        'id' => $instruction->id,
                        'instruction' => $instruction->encrypted_value->reveal(),
                        'source' => $instruction->source,
                        'effectiveAt' => $instruction->effective_at->toAtomString(),
                        'endedAt' => $instruction->ended_at?->toAtomString(),
                    ])->values()
                    : [],
                'timeline' => $serviceProjection ? $party->timelineEvents->map(fn ($event): array => [
                    'id' => $event->id,
                    'type' => $event->type->value,
                    'summary' => $event->summary,
                    'occurredAt' => $event->occurred_at->toAtomString(),
                ])->values() : [],
                'supporterSafe' => $supporterSafe,
                'administrativeMetadata' => $administrativeMetadata,
            ],
            'canUpdate' => $canUpdate,
            'canRecordConsent' => Gate::allows('recordConsent', $party),
            'canManageSafeContact' => $canManageSafeContact,
            'canManagePortalAccess' => $canManagePortalAccess,
            'portalAccessUrl' => $canManagePortalAccess ? $request->session()->get('portal_access_url') : null,
            'relationshipCandidates' => $serviceProjection ? $this->visibleParties($request->user(), $currentOrganisation)
                ->whereKeyNot($party->id)
                ->orderBy('display_name')
                ->limit(100)
                ->get(['id', 'uuid', 'display_name'])
                ->map(fn (Party $candidate): array => [
                    'id' => $candidate->id,
                    'uuid' => $candidate->uuid,
                    'displayName' => $candidate->display_name,
                ]) : [],
            ...($canUpdate ? $this->formOptions($currentOrganisation, $request->user()) : $this->emptyFormOptions()),
        ]);
    }

    public function update(
        UpdatePartyProfileRequest $request,
        Organisation $currentOrganisation,
        string $party,
        UpdatePartyProfile $updatePartyProfile,
    ): RedirectResponse {
        $party = $this->findParty($party);
        Gate::authorize('update', $party);
        $attributes = $this->profileAttributes($request);
        $this->ensureProgramScope($request->user(), $currentOrganisation, $attributes['program_ids']);
        $updatePartyProfile->handle($party, $attributes, $request->user());

        return back();
    }

    /**
     * @return array{kind: PartyKind, display_name: string, email: string|null, telephone: string|null, program_ids: list<int>, roles: list<PartyBusinessRole>, interests: list<string>}
     */
    private function profileAttributes(StorePartyProfileRequest $request): array
    {
        return [
            'kind' => PartyKind::from($request->string('kind')->toString()),
            'display_name' => $request->string('display_name')->toString(),
            'email' => $request->filled('email') ? $request->string('email')->toString() : null,
            'telephone' => $request->filled('telephone') ? $request->string('telephone')->toString() : null,
            'program_ids' => array_values(array_map(intval(...), $request->array('program_ids'))),
            'roles' => array_values(array_map(fn (mixed $role): PartyBusinessRole => PartyBusinessRole::from((string) $role), $request->array('roles'))),
            'interests' => array_values(array_map(strval(...), $request->array('interests'))),
        ];
    }

    /** @return array{programs: mixed, partyKinds: list<array{value: string, label: string}>, partyRoles: list<array{value: string, label: string}>} */
    private function formOptions(Organisation $organisation, User $user): array
    {
        return [
            'programs' => $this->managedPrograms($organisation, $user)->orderBy('name')->get(['id', 'name', 'slug']),
            'partyKinds' => array_values(collect(PartyKind::cases())->map(fn (PartyKind $kind): array => [
                'value' => $kind->value,
                'label' => ucfirst($kind->value),
            ])->all()),
            'partyRoles' => array_values(collect(PartyBusinessRole::cases())->map(fn (PartyBusinessRole $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
            ])->all()),
        ];
    }

    /** @return array{programs: array<never>, partyKinds: array<never>, partyRoles: array<never>} */
    private function emptyFormOptions(): array
    {
        return ['programs' => [], 'partyKinds' => [], 'partyRoles' => []];
    }

    /** @param list<int> $programIds */
    private function ensureProgramScope(User $user, Organisation $organisation, array $programIds): void
    {
        if ($programIds === []) {
            throw ValidationException::withMessages(['program_ids' => 'A Program-managed Party must belong to at least one Program.']);
        }

        $allowedIds = $this->managedPrograms($organisation, $user)->pluck('id');
        if (collect($programIds)->diff($allowedIds)->isNotEmpty()) {
            throw ValidationException::withMessages(['program_ids' => 'You may only manage Parties in your assigned Programs.']);
        }
    }

    /** @return Builder<Program> */
    private function managedPrograms(Organisation $organisation, User $user): Builder
    {
        $membership = $user->organisationMembership($organisation);
        $programIds = $membership?->roleAssignments()
            ->whereNull('ended_at')
            ->where('role', OrganisationRole::ProgramManager)
            ->pluck('program_id');

        return Program::query()
            ->where('organisation_id', $organisation->id)
            ->when(! $programIds?->contains(null), fn (Builder $query) => $query->whereKey($programIds ?? []));
    }

    private function contactValue(Party $party, PartyContactType $type): ?string
    {
        $contact = $party->contactPoints->first(
            fn (PartyContactPoint $contact): bool => $contact->type === $type,
        );

        return $contact?->encrypted_value->reveal();
    }

    private function findParty(string $uuid): Party
    {
        return Party::query()->where('uuid', $uuid)->firstOrFail();
    }

    /** @return Builder<Party> */
    private function visibleParties(User $user, Organisation $organisation): Builder
    {
        if ($user->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator)) {
            return Party::query();
        }

        if ($this->hasRoleInAnyScope($user, $organisation, OrganisationRole::EngagementOfficer)) {
            return Party::query()->whereHas('businessRoles', fn (Builder $query) => $query
                ->whereIn('role', ['donor', 'volunteer', 'partner_contact', 'event_attendee']));
        }

        $membership = $user->organisationMembership($organisation);
        $programIds = $membership?->roleAssignments()
            ->whereNull('ended_at')
            ->where('role', OrganisationRole::ProgramManager)
            ->pluck('program_id');

        if ($programIds?->contains(null)) {
            return Party::query();
        }

        $membershipId = $membership?->id;

        return Party::query()->where(function (Builder $query) use ($programIds, $membershipId): void {
            $query->whereHas('programs', fn (Builder $programs) => $programs->whereKey($programIds ?? []));

            if ($membershipId !== null) {
                $query->orWhereHas('serviceCases.assignments', fn (Builder $assignments) => $assignments
                    ->where('membership_id', $membershipId)
                    ->where('status', 'active'));
            }
        });
    }

    private function hasRoleInAnyScope(User $user, Organisation $organisation, OrganisationRole $role): bool
    {
        $membership = $user->organisationMembership($organisation);

        return $membership !== null
            && ! $membership->isHeld()
            && $membership->roleAssignments()->whereNull('ended_at')->where('role', $role)->exists();
    }
}
