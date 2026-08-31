<?php

use App\Actions\CaseConfidentiality\GrantRestrictedAccess;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyKind;
use App\Enums\RestrictedAccessPermission;
use App\Models\IntakeRequest;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyConsent;
use App\Models\PartyRole;
use App\Models\PartySafeContactInstruction;
use App\Models\Program;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function partyProfileFixture(OrganisationRole $role = OrganisationRole::ProgramManager): array
{
    $user = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $membership = $organisation->memberships()->create(['user_id' => $user->id, 'role' => $role]);
    $program = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(),
    );

    if ($role === OrganisationRole::ProgramManager) {
        app(OrganisationContext::class)->run($organisation, fn () => $membership->programs()->attach($program));
    }

    return compact('user', 'organisation', 'membership', 'program');
}

it('creates a tenant-local Party profile with separate roles programs interests and encrypted contacts', function () {
    extract(partyProfileFixture());

    $response = $this->actingAs($user)->post(route('parties.store', $organisation), [
        'kind' => PartyKind::Person->value,
        'display_name' => 'Amina Example',
        'email' => 'amina@example.test',
        'telephone' => '+1 202-555-0199',
        'program_ids' => [$program->id],
        'roles' => [PartyBusinessRole::Client->value, PartyBusinessRole::Volunteer->value],
        'interests' => ['Housing', 'Food security'],
    ]);

    $party = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Party => Party::query()->where('display_name', 'Amina Example')->firstOrFail(),
    );
    $response->assertRedirect(route('parties.show', [$organisation, $party]));

    app(OrganisationContext::class)->run($organisation, function () use ($party, $program): void {
        expect($party->businessRoles()->get()->map(fn (PartyRole $role): string => $role->role->value)->all())->toEqualCanonicalizing(['client', 'volunteer'])
            ->and($party->programs()->pluck('programs.id')->all())->toBe([$program->id])
            ->and($party->interests()->pluck('label')->all())->toEqualCanonicalizing(['Housing', 'Food security'])
            ->and($party->timelineEvents()->pluck('summary')->all())->toBe(['Profile created']);
    });

    expect(DB::table('party_contact_points')->where('party_id', $party->id)->pluck('encrypted_value')->implode(' '))
        ->not->toContain('amina@example.test')
        ->not->toContain('202-555-0199');
});

it('preserves immutable consent grant and withdrawal history with exact provenance', function () {
    extract(partyProfileFixture());
    $party = app(OrganisationContext::class)->run($organisation, fn (): Party => Party::factory()->for($organisation)->create());
    $base = [
        'purpose' => ConsentPurpose::Referral->value,
        'wording_version' => 'referral-v2',
        'wording' => 'I agree that my details may be shared with the named partner.',
        'source' => 'signed_form',
        'occurred_at' => now()->subMinute()->toAtomString(),
    ];

    $this->actingAs($user)->post(route('parties.consents.store', [$organisation, $party]), [...$base, 'decision' => ConsentDecision::Granted->value])->assertRedirect();
    $this->actingAs($user)->post(route('parties.consents.store', [$organisation, $party]), [...$base, 'decision' => ConsentDecision::Withdrawn->value, 'occurred_at' => now()->toAtomString()])->assertRedirect();

    app(OrganisationContext::class)->run($organisation, function () use ($party): void {
        $history = PartyConsent::query()->where('party_id', $party->id)->oldest('occurred_at')->get();
        expect($history)->toHaveCount(2)
            ->and($history[1]->supersedes_id)->toBe($history[0]->id)
            ->and($history[0]->wording)->toBe($history[1]->wording)
            ->and(fn () => $history[0]->update(['source' => 'changed']))->toThrow(LogicException::class);
    });
});

it('limits Party safe-contact content to service staff with explicit sensitive access', function () {
    extract(partyProfileFixture(OrganisationRole::ProgramManager));
    $administrator = User::factory()->create();
    $organisation->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $party = app(OrganisationContext::class)->run($organisation, function () use ($organisation, $program, $membership, $user): Party {
        $party = Party::factory()->for($organisation)->create();
        $party->programs()->attach($program);
        $intake = IntakeRequest::factory()->create(['program_id' => $program->id, 'party_id' => $party->id]);
        $case = ServiceCase::factory()->create(['intake_request_id' => $intake->id, 'program_id' => $program->id, 'party_id' => $party->id]);
        app(GrantRestrictedAccess::class)->handle($case, $membership, RestrictedAccessPermission::SensitiveData, 'Test safeguarding access.', $user);

        return $party;
    });

    $this->actingAs($user)->post(route('parties.safe-contact-instructions.store', [$organisation, $party]), [
        'instruction' => 'Never leave a voicemail.',
        'source' => 'participant',
        'effective_at' => now()->toAtomString(),
    ])->assertRedirect();
    $this->actingAs($administrator)->post(route('parties.safe-contact-instructions.store', [$organisation, $party]), [
        'instruction' => 'Unsafe admin access',
        'source' => 'admin',
        'effective_at' => now()->toAtomString(),
    ])->assertForbidden();

    expect(DB::table('party_safe_contact_instructions')->value('encrypted_value'))->not->toContain('Never leave a voicemail');
    app(OrganisationContext::class)->run($organisation, fn () => expect(PartySafeContactInstruction::query()->count())->toBe(1));
});

it('lets an Organisation-wide Program manager open an unassigned Party', function () {
    extract(partyProfileFixture(OrganisationRole::ProgramManager));
    $party = app(OrganisationContext::class)->run($organisation, fn (): Party => Party::factory()->for($organisation)->create());

    $this->actingAs($user)
        ->get(route('parties.show', [$organisation, $party]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('party.uuid', $party->uuid));
});

it('enforces consent history immutability and tenant-local supersession in the database', function () {
    extract(partyProfileFixture());
    $party = app(OrganisationContext::class)->run($organisation, fn (): Party => Party::factory()->for($organisation)->create());
    $consent = app(OrganisationContext::class)->run($organisation, fn (): PartyConsent => PartyConsent::factory()->for($party)->create());
    $otherOrganisation = Organisation::factory()->active()->create();
    $otherParty = app(OrganisationContext::class)->run($otherOrganisation, fn (): Party => Party::factory()->for($otherOrganisation)->create());

    expect(fn () => DB::table('party_consents')->where('id', $consent->id)->update(['source' => 'changed']))
        ->toThrow(QueryException::class)
        ->and(fn () => DB::table('party_consents')->insert([
            'id' => (string) str()->ulid(),
            'organisation_id' => $otherOrganisation->id,
            'party_id' => $otherParty->id,
            'purpose' => ConsentPurpose::Service->value,
            'decision' => ConsentDecision::Withdrawn->value,
            'wording_version' => 'v1',
            'wording' => 'Wording',
            'source' => 'test',
            'occurred_at' => now(),
            'supersedes_id' => $consent->id,
        ]))->toThrow(QueryException::class);
});

it('returns not found for another tenant Party and rejects cross-tenant pivots', function () {
    extract(partyProfileFixture());
    $otherOrganisation = Organisation::factory()->active()->create();
    $otherParty = app(OrganisationContext::class)->run($otherOrganisation, fn (): Party => Party::factory()->for($otherOrganisation)->create());

    $this->actingAs($user)->get(route('parties.show', [$organisation, $otherParty]))->assertNotFound();

    expect(fn () => DB::table('party_program')->insert([
        'organisation_id' => $organisation->id,
        'party_id' => $otherParty->id,
        'program_id' => $program->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('does not grant Party access through ownership alone', function () {
    $owner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create(['user_id' => $owner->id, 'is_owner' => true]);

    $this->actingAs($owner)->get(route('parties.index', $organisation))->assertForbidden();
});
