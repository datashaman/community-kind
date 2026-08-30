<?php

use App\Actions\Organisations\AcceptOrganisationInvitation;
use App\Actions\Organisations\IssueOrganisationInvitation;
use App\Enums\OrganisationRole;
use App\Enums\PartyKind;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureRecentPassword;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\ProtectSensitiveFortifyRoutes;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\Program;
use App\Models\User;
use App\OrganisationContext;
use App\Policies\ProgramPolicy;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureStaffSecurityRequirements::class,
        EnsureRecentMfa::class,
        EnsureRecentPassword::class,
        ProtectSensitiveFortifyRoutes::class,
    ]);
});

function createMembership(Organisation $organisation, User $user, ?OrganisationRole $role = null, bool $isOwner = false): Membership
{
    $organisation->members()->attach($user, [
        'role' => $role?->value,
        'is_owner' => $isOwner,
    ]);

    return $organisation->memberships()->where('user_id', $user->id)->firstOrFail();
}

test('verified invitation acceptance links the explicit Party and creates independently scoped roles', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->unverified()->create(['email' => 'sam@example.test']);
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);

    [$programA, $programB, $party] = app(OrganisationContext::class)->run($organisation, function () use ($organisation) {
        return [
            Program::factory()->create(['organisation_id' => $organisation->id]),
            Program::factory()->create(['organisation_id' => $organisation->id]),
            Party::factory()->create([
                'organisation_id' => $organisation->id,
                'kind' => PartyKind::Person,
                'display_name' => 'Sam Person',
            ]),
        ];
    });

    $issued = app(IssueOrganisationInvitation::class)->handle(
        $organisation,
        $owner,
        $invitedUser->email,
        $party->id,
        null,
        [
            ['role' => OrganisationRole::ProgramManager->value, 'program_id' => $programA->id],
            ['role' => OrganisationRole::CaseWorker->value, 'program_id' => $programB->id],
        ],
    );

    expect(fn () => app(AcceptOrganisationInvitation::class)->handle($invitedUser, $issued->invitation))
        ->toThrow(ValidationException::class)
        ->and($organisation->memberships()->where('user_id', $invitedUser->id)->exists())->toBeFalse();

    $invitedUser->markEmailAsVerified();
    app(AcceptOrganisationInvitation::class)->handle($invitedUser, $issued->invitation);

    $membership = $organisation->memberships()->where('user_id', $invitedUser->id)->firstOrFail();
    $assignments = app(OrganisationContext::class)->run(
        $organisation,
        fn () => $membership->roleAssignments()->whereNull('ended_at')->get(),
    );

    expect($membership->person_party_id)->toBe($party->id)
        ->and($assignments)->toHaveCount(2)
        ->and($assignments->pluck('program_id')->all())->toEqualCanonicalizing([$programA->id, $programB->id]);
});

test('email matching never selects an existing person Party', function () {
    $owner = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'person@example.test']);
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);

    $existingParty = app(OrganisationContext::class)->run(
        $organisation,
        fn () => Party::factory()->create([
            'organisation_id' => $organisation->id,
            'kind' => PartyKind::Person,
            'display_name' => $invitedUser->email,
        ]),
    );

    $issued = app(IssueOrganisationInvitation::class)->handle(
        $organisation,
        $owner,
        $invitedUser->email,
        null,
        'Different Person',
        [['role' => OrganisationRole::CaseWorker->value, 'program_id' => null]],
    );
    app(AcceptOrganisationInvitation::class)->handle($invitedUser, $issued->invitation);

    $membership = $organisation->memberships()->where('user_id', $invitedUser->id)->firstOrFail();

    expect($membership->person_party_id)->not->toBe($existingParty->id);
});

test('Owner responsibility is separate and accepted only through an Owner-issued invitation', function () {
    $owner = User::factory()->create();
    $administrator = User::factory()->create();
    $invitedUser = User::factory()->create(['email' => 'new-owner@example.test']);
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);
    createMembership($organisation, $administrator, OrganisationRole::OrganisationAdministrator);

    expect(fn () => app(IssueOrganisationInvitation::class)->handle(
        $organisation,
        $administrator,
        $invitedUser->email,
        null,
        'New Owner',
        [['role' => OrganisationRole::ExecutiveViewer->value, 'program_id' => null]],
        true,
    ))->toThrow(ValidationException::class);

    $issued = app(IssueOrganisationInvitation::class)->handle(
        $organisation,
        $owner,
        $invitedUser->email,
        null,
        'New Owner',
        [['role' => OrganisationRole::ExecutiveViewer->value, 'program_id' => null]],
        true,
    );
    app(AcceptOrganisationInvitation::class)->handle($invitedUser, $issued->invitation);

    expect($invitedUser->fresh()->ownsOrganisation($organisation))->toBeTrue()
        ->and($invitedUser->hasOrganisationRole($organisation, OrganisationRole::ExecutiveViewer))->toBeTrue();
});

test('rejoining creates a new tenure and retains the ended Membership', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['email' => 'returning@example.test']);
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);
    $endedMembership = createMembership($organisation, $member, OrganisationRole::CaseWorker);

    app(OrganisationContext::class)->run($organisation, fn () => $endedMembership->end());
    $partyId = $endedMembership->person_party_id;
    $issued = app(IssueOrganisationInvitation::class)->handle(
        $organisation,
        $owner,
        $member->email,
        $partyId,
        null,
        [['role' => OrganisationRole::EngagementOfficer->value, 'program_id' => null]],
    );
    app(AcceptOrganisationInvitation::class)->handle($member, $issued->invitation);

    $history = $organisation->membershipHistory()->where('user_id', $member->id)->orderBy('id')->get();

    expect($history)->toHaveCount(2)
        ->and($history->first()->ended_at)->not->toBeNull()
        ->and($history->last()->id)->not->toBe($endedMembership->id)
        ->and($history->last()->person_party_id)->toBe($partyId);
});

test('an ended tenure cannot be nominated for Organisation ownership', function () {
    $owner = User::factory()->create();
    $formerMember = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);
    $membership = createMembership($organisation, $formerMember, OrganisationRole::CaseWorker);
    app(OrganisationContext::class)->run($organisation, fn () => $membership->end());

    $this->actingAs($owner)
        ->post(route('organisations.ownership-transfers.store', $organisation), [
            'nominee_user_id' => $formerMember->id,
        ])
        ->assertSessionHasErrors('nominee_user_id');
});

test('different roles contribute only within their independent scopes', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);
    $membership = createMembership($organisation, $member);

    [$managedProgram, $caseProgram, $otherProgram] = app(OrganisationContext::class)->run(
        $organisation,
        fn () => [
            Program::factory()->create(['organisation_id' => $organisation->id]),
            Program::factory()->create(['organisation_id' => $organisation->id]),
            Program::factory()->create(['organisation_id' => $organisation->id]),
        ],
    );

    app(OrganisationContext::class)->run($organisation, function () use ($membership, $organisation, $managedProgram, $caseProgram): void {
        $membership->roleAssignments()->createMany([
            ['organisation_id' => $organisation->id, 'role' => OrganisationRole::ProgramManager, 'program_id' => $managedProgram->id],
            ['organisation_id' => $organisation->id, 'role' => OrganisationRole::CaseWorker, 'program_id' => $caseProgram->id],
        ]);
    });

    app(OrganisationContext::class)->run($organisation, function () use ($owner, $member, $managedProgram, $caseProgram, $otherProgram): void {
        $policy = app(ProgramPolicy::class);

        expect($policy->update($member, $managedProgram))->toBeTrue()
            ->and($policy->view($member, $caseProgram))->toBeTrue()
            ->and($policy->update($member, $caseProgram))->toBeFalse()
            ->and($policy->view($member, $otherProgram))->toBeFalse()
            ->and($policy->view($owner, $managedProgram))->toBeFalse();
    });
});

test('Organisation Administrators cannot self-escalate or appoint another administrator', function () {
    $owner = User::factory()->create();
    $administrator = User::factory()->create();
    $member = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);
    createMembership($organisation, $administrator, OrganisationRole::OrganisationAdministrator);
    createMembership($organisation, $member, OrganisationRole::CaseWorker);

    $this->actingAs($administrator)
        ->patch(route('organisations.members.update', [$organisation, $administrator]), [
            'role_assignments' => [['role' => OrganisationRole::ProgramManager->value, 'program_id' => null]],
        ])
        ->assertSessionHasErrors('role_assignments');

    $this->actingAs($administrator)
        ->patch(route('organisations.members.update', [$organisation, $member]), [
            'role_assignments' => [['role' => OrganisationRole::OrganisationAdministrator->value, 'program_id' => null]],
        ])
        ->assertSessionHasErrors('role_assignments');
});

test('duplicate role and scope proposals are rejected before an invitation is created', function () {
    $owner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);

    $this->actingAs($owner)
        ->post(route('organisations.invitations.store', $organisation), [
            'email' => 'duplicate@example.test',
            'new_person_name' => 'Duplicate Example',
            'role_assignments' => [
                ['role' => OrganisationRole::CaseWorker->value, 'program_id' => null],
                ['role' => OrganisationRole::CaseWorker->value, 'program_id' => null],
            ],
        ])
        ->assertSessionHasErrors('role_assignments');

    $this->assertDatabaseMissing('organisation_invitations', ['email' => 'duplicate@example.test']);
});

test('a Membership Hold pauses only that Membership and preserves capable owner continuity', function () {
    $owner = User::factory()->create();
    $secondOwner = User::factory()->create();
    $member = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);
    createMembership($organisation, $secondOwner, isOwner: true);
    createMembership($organisation, $member, OrganisationRole::OrganisationAdministrator);

    $this->actingAs($owner)
        ->post(route('organisations.members.holds.store', [$organisation, $member]), [
            'reason' => 'Temporary safeguarding review',
            'review_at' => now()->addDay()->toISOString(),
        ])
        ->assertRedirect();

    app(OrganisationContext::class)->run($organisation, function () use ($member, $organisation): void {
        expect($member->fresh()->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator))->toBeFalse();
    });

    $hold = app(OrganisationContext::class)->run(
        $organisation,
        fn () => $organisation->memberships()->where('user_id', $member->id)->firstOrFail()->holds()->firstOrFail(),
    );
    $this->actingAs($owner)
        ->delete(route('organisations.members.holds.destroy', [$organisation, $member, $hold]))
        ->assertRedirect();

    app(OrganisationContext::class)->run($organisation, function () use ($member, $organisation): void {
        expect($member->fresh()->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator))->toBeTrue();
    });

    $this->actingAs($owner)
        ->post(route('organisations.members.holds.store', [$organisation, $secondOwner]), [
            'reason' => 'Temporary governance review',
            'review_at' => now()->addDay()->toISOString(),
        ])
        ->assertRedirect();

    $this->actingAs($owner)
        ->post(route('organisations.members.holds.store', [$organisation, $owner]), [
            'reason' => 'Would remove final capable owner',
            'review_at' => now()->addDay()->toISOString(),
        ])
        ->assertForbidden();
});

test('an invitation cannot select a person Party from another Organisation', function () {
    $owner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $otherOrganisation = Organisation::factory()->active()->create();
    createMembership($organisation, $owner, isOwner: true);
    $foreignParty = app(OrganisationContext::class)->run(
        $otherOrganisation,
        fn () => Party::factory()->create([
            'organisation_id' => $otherOrganisation->id,
            'kind' => PartyKind::Person,
        ]),
    );

    $this->actingAs($owner)
        ->post(route('organisations.invitations.store', $organisation), [
            'email' => 'invitee@example.test',
            'person_party_id' => $foreignParty->id,
            'role_assignments' => [['role' => OrganisationRole::CaseWorker->value, 'program_id' => null]],
        ])
        ->assertSessionHasErrors('person_party_id');
});
