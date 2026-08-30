<?php

namespace Tests\Feature\Organisations;

use App\Enums\OrganisationRole;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureRecentPassword;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\ProtectSensitiveFortifyRoutes;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use App\Notifications\Organisations\OrganisationInvitation as OrganisationInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganisationInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureStaffSecurityRequirements::class,
            EnsureRecentMfa::class,
            EnsureRecentPassword::class,
            ProtectSensitiveFortifyRoutes::class,
        ]);
    }

    public function test_organisation_invitations_can_be_created()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $response = $this
            ->actingAs($owner)
            ->post(route('organisations.invitations.store', $organisation), [
                'email' => 'invited@example.com',
                'new_person_name' => 'Invited Person',
                'role' => OrganisationRole::CaseWorker->value,
            ]);

        $response->assertRedirect(route('organisations.edit', $organisation));

        $this->assertDatabaseHas('organisation_invitations', [
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'role' => OrganisationRole::CaseWorker->value,
        ]);
    }

    public function test_invitation_email_for_existing_users_uses_login_route()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $token = 'existing-user-invitation-token';
        $invitation = OrganisationInvitation::factory()->forToken($token)->create([
            'organisation_id' => $organisation->id,
            'email' => $invitedUser->email,
            'invited_by' => $owner->id,
        ]);

        $mail = (new OrganisationInvitationNotification($invitation, $token, true))->toMail($invitedUser);

        $this->assertSame(route('login', ['invitation' => $token]), $mail->actionUrl);
        $this->assertStringContainsString('dashboard', implode(' ', $mail->introLines));
    }

    public function test_invitation_email_for_unknown_users_uses_registration_route()
    {
        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $token = 'new-user-invitation-token';
        $invitation = OrganisationInvitation::factory()->forToken($token)->create([
            'organisation_id' => $organisation->id,
            'email' => 'unknown@example.com',
            'invited_by' => $owner->id,
        ]);

        $mail = (new OrganisationInvitationNotification($invitation, $token, false))->toMail((object) []);

        $this->assertSame(route('register', ['invitation' => $token]), $mail->actionUrl);
        $this->assertStringContainsString('create', strtolower(implode(' ', $mail->introLines)));
    }

    public function test_organisation_invitations_can_be_created_by_admins()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($admin, ['role' => OrganisationRole::OrganisationAdministrator->value]);

        $response = $this
            ->actingAs($admin)
            ->post(route('organisations.invitations.store', $organisation), [
                'email' => 'invited@example.com',
                'new_person_name' => 'Invited Person',
                'role' => OrganisationRole::CaseWorker->value,
            ]);

        $response->assertRedirect(route('organisations.edit', $organisation));
    }

    public function test_organisation_invitations_cannot_assign_the_owner_role()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($admin, ['role' => OrganisationRole::OrganisationAdministrator->value]);

        $response = $this
            ->actingAs($admin)
            ->post(route('organisations.invitations.store', $organisation), [
                'email' => 'invited@example.com',
                'new_person_name' => 'Invited Person',
                'role' => 'owner',
            ]);

        $response->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('organisation_invitations', [
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
        ]);
        Notification::assertNothingSent();
    }

    public function test_existing_organisation_members_cannot_be_invited()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->post(route('organisations.invitations.store', $organisation), [
                'email' => 'member@example.com',
                'new_person_name' => 'Existing Member',
                'role' => OrganisationRole::CaseWorker->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_duplicate_invitations_cannot_be_created()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();
        $organisation->members()->attach($owner, ['is_owner' => true]);

        OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('organisations.invitations.store', $organisation), [
                'email' => 'invited@example.com',
                'new_person_name' => 'Invited Person',
                'role' => OrganisationRole::CaseWorker->value,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_revoked_invitations_can_be_reissued()
    {
        Notification::fake();

        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();
        $organisation->members()->attach($owner, ['is_owner' => true]);
        OrganisationInvitation::factory()->revoked()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->post(route('organisations.invitations.store', $organisation), [
                'email' => 'invited@example.com',
                'new_person_name' => 'Invited Person',
                'role' => OrganisationRole::CaseWorker->value,
            ]);

        $response->assertRedirect(route('organisations.edit', $organisation));
        $this->assertDatabaseCount('organisation_invitations', 2);
        Notification::assertCount(1);
    }

    public function test_revoked_invitations_are_not_shown_as_pending()
    {
        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();
        $organisation->members()->attach($owner, ['is_owner' => true]);
        OrganisationInvitation::factory()->revoked()->create([
            'organisation_id' => $organisation->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->get(route('organisations.edit', $organisation));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('organisations/edit')
            ->has('invitations', 0),
        );
    }

    public function test_organisation_invitations_cannot_be_created_by_members()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($member)
            ->post(route('organisations.invitations.store', $organisation), [
                'email' => 'invited@example.com',
                'new_person_name' => 'Invited Person',
                'role' => OrganisationRole::CaseWorker->value,
            ]);

        $response->assertForbidden();
    }

    public function test_organisation_invitations_can_be_cancelled_by_owners()
    {
        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('organisations.invitations.destroy', [$organisation, $invitation]));

        $response->assertRedirect(route('organisations.edit', $organisation));

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $invitation->id,
            'revoked_by' => $owner->id,
        ]);
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_organisation_invitations_can_be_accepted()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'role' => OrganisationRole::CaseWorker,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertRedirect(route('dashboard'));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Invitation accepted.']);

        $this->assertTrue($invitedUser->fresh()->belongsToOrganisation($organisation));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_organisation_invitations_can_be_declined_by_the_invited_user()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $invitation->id,
            'revoked_by' => $invitedUser->id,
        ]);
        $this->assertNotNull($invitation->fresh()->revoked_at);
    }

    public function test_organisation_invitations_cannot_be_declined_by_uninvited_user()
    {
        $owner = User::factory()->create();
        $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_accepted_organisation_invitations_cannot_be_declined()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->accepted()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->delete(route('invitations.decline', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_organisation_invitations_cannot_be_accepted_by_uninvited_user()
    {
        $owner = User::factory()->create();
        $uninvitedUser = User::factory()->create(['email' => 'uninvited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($uninvitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($uninvitedUser->fresh()->belongsToOrganisation($organisation));
    }

    public function test_expired_invitations_cannot_be_accepted()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->expired()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->post(route('invitations.accept', $invitation));

        $response->assertSessionHasErrors('invitation');

        $this->assertFalse($invitedUser->fresh()->belongsToOrganisation($organisation));
    }
}
