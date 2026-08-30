<?php

namespace Tests\Feature\Organisations;

use App\Enums\OrganisationRole;
use App\Http\Middleware\EnsureRecentMfa;
use App\Http\Middleware\EnsureRecentPassword;
use App\Http\Middleware\EnsureStaffSecurityRequirements;
use App\Http\Middleware\ProtectSensitiveFortifyRoutes;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrganisationTest extends TestCase
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

    public function test_the_organisations_index_page_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('organisations.index'));

        $response->assertOk();
    }

    public function test_organisations_can_be_created()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('organisations.store'), [
                'name' => 'Test Organisation',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('organisations', [
            'name' => 'Test Organisation',
        ]);
    }

    public function test_organisation_creation_can_be_disabled(): void
    {
        config(['organisations.self_service_provisioning' => false]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('organisations.store'), [
                'name' => 'Test Organisation',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('organisations', [
            'name' => 'Test Organisation',
        ]);
    }

    public function test_organisation_creation_policy_is_shared_with_the_frontend(): void
    {
        config(['organisations.self_service_provisioning' => false]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('organisations.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canCreateOrganisation', false),
            );
    }

    public function test_organisation_names_must_produce_a_non_empty_slug()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('organisations.store'), [
                'name' => '!!!',
            ]);

        $response->assertSessionHasErrors([
            'name' => 'This organisation name must contain at least one letter or number.',
        ]);

        $this->assertDatabaseMissing('organisations', [
            'name' => '!!!',
        ]);
    }

    public function test_organisation_slug_uses_next_available_suffix()
    {
        $user = User::factory()->create();

        Organisation::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Organisation::factory()->create(['name' => 'Acme One', 'slug' => 'acme-1']);
        Organisation::factory()->create(['name' => 'Acme Ten', 'slug' => 'acme-10']);

        $this
            ->actingAs($user)
            ->post(route('organisations.store'), [
                'name' => 'Acme',
            ]);

        $this->assertDatabaseHas('organisations', [
            'name' => 'Acme',
            'slug' => 'acme-11',
        ]);
    }

    public function test_the_organisation_edit_page_can_be_rendered()
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($user, ['is_owner' => true]);

        $response = $this
            ->actingAs($user)
            ->get(route('organisations.edit', $organisation));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('organisations/edit')
                ->where('members.0.role', null)
                ->where('members.0.role_label', 'No operational role')
                ->where('members.0.is_owner', true),
            );
    }

    public function test_organisations_can_be_updated_by_owners()
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create(['name' => 'Original Name']);

        $organisation->members()->attach($user, ['is_owner' => true]);

        $response = $this
            ->actingAs($user)
            ->patch(route('organisations.update', $organisation), [
                'name' => 'Updated Name',
            ]);

        $response->assertRedirect(route('organisations.edit', $organisation->fresh()));

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_organisations_cannot_be_updated_by_members()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($member)
            ->patch(route('organisations.update', $organisation), [
                'name' => 'Updated Name',
            ]);

        $response->assertForbidden();
    }

    public function test_organisations_can_be_deleted_by_owners()
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($user, ['is_owner' => true]);

        $response = $this
            ->actingAs($user)
            ->delete(route('organisations.destroy', $organisation), [
                'name' => $organisation->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('organisations', [
            'id' => $organisation->id,
        ]);
    }

    public function test_organisation_deletion_requires_name_confirmation()
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($user, ['is_owner' => true]);

        $response = $this
            ->actingAs($user)
            ->delete(route('organisations.destroy', $organisation), [
                'name' => 'Wrong Name',
            ]);

        $response->assertSessionHasErrors('name');

        $this->assertDatabaseHas('organisations', [
            'id' => $organisation->id,
            'deleted_at' => null,
        ]);
    }

    public function test_deleting_current_organisation_switches_to_alphabetically_first_remaining_organisation()
    {
        $user = User::factory()->create(['name' => 'Mike']);

        $zuluOrganisation = Organisation::factory()->create(['name' => 'Zulu Organisation']);
        $zuluOrganisation->members()->attach($user, ['is_owner' => true]);

        $alphaOrganisation = Organisation::factory()->create(['name' => 'Alpha Organisation']);
        $alphaOrganisation->members()->attach($user, ['is_owner' => true]);

        $betaOrganisation = Organisation::factory()->create(['name' => 'Beta Organisation']);
        $betaOrganisation->members()->attach($user, ['is_owner' => true]);

        $user->update(['current_organisation_id' => $zuluOrganisation->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('organisations.destroy', $zuluOrganisation), [
                'name' => $zuluOrganisation->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('organisations', [
            'id' => $zuluOrganisation->id,
        ]);

        $this->assertEquals($alphaOrganisation->id, $user->fresh()->current_organisation_id);
    }

    public function test_deleting_current_organisation_falls_back_to_the_remaining_organisation()
    {
        $user = User::factory()->create();
        $remainingOrganisation = $user->currentOrganisation;
        $organisation = Organisation::factory()->create(['name' => 'Zulu Organisation']);
        $organisation->members()->attach($user, ['is_owner' => true]);

        $user->update(['current_organisation_id' => $organisation->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('organisations.destroy', $organisation), [
                'name' => $organisation->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('organisations', [
            'id' => $organisation->id,
        ]);

        $this->assertEquals($remainingOrganisation->id, $user->fresh()->current_organisation_id);
    }

    public function test_deleting_non_current_organisation_leaves_current_organisation_unchanged()
    {
        $user = User::factory()->create();
        $currentOrganisation = $user->currentOrganisation;
        $organisation = Organisation::factory()->create();
        $organisation->members()->attach($user, ['is_owner' => true]);

        $user->update(['current_organisation_id' => $currentOrganisation->id]);

        $response = $this
            ->actingAs($user)
            ->delete(route('organisations.destroy', $organisation), [
                'name' => $organisation->name,
            ]);

        $response->assertRedirect();

        $this->assertSoftDeleted('organisations', [
            'id' => $organisation->id,
        ]);

        $this->assertEquals($currentOrganisation->id, $user->fresh()->current_organisation_id);
    }

    public function test_members_can_leave_non_personal_organisations()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($member)
            ->delete(route('organisations.leave', $organisation));

        $response->assertRedirect(route('organisations.index'));
        $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => "You left the organisation \"{$organisation->name}\""]);

        $this->assertFalse($member->fresh()->belongsToOrganisation($organisation));
    }

    public function test_leaving_current_organisation_switches_to_alphabetically_first_remaining_organisation()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['name' => 'Mike']);

        $zuluOrganisation = Organisation::factory()->create(['name' => 'Zulu Organisation']);
        $zuluOrganisation->members()->attach($owner, ['is_owner' => true]);
        $zuluOrganisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $alphaOrganisation = Organisation::factory()->create(['name' => 'Alpha Organisation']);
        $alphaOrganisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $betaOrganisation = Organisation::factory()->create(['name' => 'Beta Organisation']);
        $betaOrganisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $member->update(['current_organisation_id' => $zuluOrganisation->id]);

        $response = $this
            ->actingAs($member)
            ->delete(route('organisations.leave', $zuluOrganisation));

        $response->assertRedirect(route('organisations.index'));

        $this->assertFalse($member->fresh()->belongsToOrganisation($zuluOrganisation));
        $this->assertEquals($alphaOrganisation->id, $member->fresh()->current_organisation_id);
    }

    public function test_organisation_owners_cannot_leave_their_organisation()
    {
        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('organisations.leave', $organisation));

        $response->assertForbidden();

        $this->assertTrue($owner->fresh()->belongsToOrganisation($organisation));
    }

    public function test_users_cannot_leave_organisations_they_dont_belong_to()
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete(route('organisations.leave', $organisation));

        $response->assertForbidden();
    }

    public function test_deleting_organisation_switches_other_affected_users_to_their_remaining_organisation()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $memberRemainingOrganisation = $member->currentOrganisation;

        $organisation = Organisation::factory()->create();
        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $owner->update(['current_organisation_id' => $organisation->id]);
        $member->update(['current_organisation_id' => $organisation->id]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('organisations.destroy', $organisation), [
                'name' => $organisation->name,
            ]);

        $response->assertRedirect();

        $this->assertEquals($memberRemainingOrganisation->id, $member->fresh()->current_organisation_id);
    }

    public function test_organisations_cannot_be_deleted_by_non_owners()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($member)
            ->delete(route('organisations.destroy', $organisation), [
                'name' => $organisation->name,
            ]);

        $response->assertForbidden();
    }

    public function test_users_can_switch_organisations()
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($user, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($user)
            ->post(route('organisations.switch', $organisation));

        $response->assertRedirect();

        $this->assertEquals($organisation->id, $user->fresh()->current_organisation_id);
    }

    public function test_users_cannot_switch_to_organisation_they_dont_belong_to()
    {
        $user = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $response = $this
            ->actingAs($user)
            ->post(route('organisations.switch', $organisation));

        $response->assertForbidden();
    }

    public function test_guests_cannot_access_organisations()
    {
        $response = $this->get(route('organisations.index'));

        $response->assertRedirect(route('login'));
    }
}
