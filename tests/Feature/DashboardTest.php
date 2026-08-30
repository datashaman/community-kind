<?php

namespace Tests\Feature;

use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $user = User::factory()->create();
        $organisation = $user->currentOrganisation;

        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $organisation = $user->currentOrganisation;

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertOk();
    }

    public function test_unverified_users_are_redirected_to_the_email_verification_screen()
    {
        $user = User::factory()->unverified()->create();
        $organisation = $user->currentOrganisation;

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_dashboard_includes_pending_invitations_for_the_authenticated_user()
    {
        $owner = User::factory()->create(['name' => 'Taylor Otwell']);
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create(['name' => 'Laravel Organisation']);

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'new_person_name' => 'Invited Person',
            'offers_ownership' => true,
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 1)
            ->where('pendingInvitations.0.id', $invitation->id)
            ->where('pendingInvitations.0.inviterName', 'Taylor Otwell')
            ->where('pendingInvitations.0.personName', 'Invited Person')
            ->where('pendingInvitations.0.offersOwnership', true)
            ->where('pendingInvitations.0.roleAssignments.0.roleLabel', 'Case worker')
            ->where('pendingInvitations.0.roleAssignments.0.scopeLabel', 'Organisation-wide')
            ->where('pendingInvitations.0.organisation.name', 'Laravel Organisation')
            ->where('pendingInvitations.0.organisation.slug', $organisation->slug)
            ->missing('pendingInvitations.0.organisationName'),
        );
    }

    public function test_dashboard_does_not_include_accepted_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        OrganisationInvitation::factory()->accepted()->create([
            'organisation_id' => $organisation->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );
    }

    public function test_dashboard_excludes_expired_invitations_without_deleting_them()
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
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $invitation->id,
        ]);
    }

    public function test_dashboard_does_not_include_or_delete_other_users_invitations()
    {
        $owner = User::factory()->create();
        $invitedUser = User::factory()->create(['email' => 'invited@example.com']);
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $invitation = OrganisationInvitation::factory()->expired()->create([
            'organisation_id' => $organisation->id,
            'email' => 'someone@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this
            ->actingAs($invitedUser)
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('pendingInvitations', 0),
        );

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $invitation->id,
        ]);
    }
}
