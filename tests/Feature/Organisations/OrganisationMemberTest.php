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
use Tests\TestCase;

class OrganisationMemberTest extends TestCase
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

    public function test_organisation_member_roles_can_be_updated_by_owners()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->patch(route('organisations.members.update', [$organisation, $member]), [
                'role' => OrganisationRole::OrganisationAdministrator->value,
            ]);

        $response->assertRedirect(route('organisations.edit', $organisation));

        $this->assertEquals(
            OrganisationRole::OrganisationAdministrator->value,
            $organisation->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_organisation_member_roles_cannot_be_updated_by_program_managers()
    {
        $owner = User::factory()->create();
        $programManager = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($programManager, ['role' => OrganisationRole::ProgramManager->value]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($programManager)
            ->patch(route('organisations.members.update', [$organisation, $member]), [
                'role' => OrganisationRole::OrganisationAdministrator->value,
            ]);

        $response->assertForbidden();
    }

    public function test_organisation_members_can_be_removed_by_owners()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('organisations.members.destroy', [$organisation, $member]));

        $response->assertRedirect(route('organisations.edit', $organisation));

        $this->assertFalse($member->fresh()->belongsToOrganisation($organisation));
    }

    public function test_organisation_members_cannot_be_removed_by_program_managers()
    {
        $owner = User::factory()->create();
        $programManager = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($programManager, ['role' => OrganisationRole::ProgramManager->value]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($programManager)
            ->delete(route('organisations.members.destroy', [$organisation, $member]));

        $response->assertForbidden();
    }

    public function test_organisation_owner_cannot_be_removed()
    {
        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $response = $this
            ->actingAs($owner)
            ->delete(route('organisations.members.destroy', [$organisation, $owner]));

        $response->assertForbidden();

        $this->assertTrue($owner->fresh()->belongsToOrganisation($organisation));
    }

    public function test_organisation_member_role_cannot_be_set_to_owner()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $response = $this
            ->actingAs($owner)
            ->patch(route('organisations.members.update', [$organisation, $member]), [
                'role' => 'owner',
            ]);

        $response->assertSessionHasErrors('role');

        $this->assertEquals(
            OrganisationRole::CaseWorker->value,
            $organisation->members()->where('user_id', $member->id)->first()->pivot->role->value,
        );
    }

    public function test_removed_member_current_organisation_is_set_to_their_remaining_organisation()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $remainingOrganisation = $member->currentOrganisation;
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);
        $organisation->members()->attach($member, ['role' => OrganisationRole::CaseWorker->value]);

        $member->update(['current_organisation_id' => $organisation->id]);

        $this
            ->actingAs($owner)
            ->delete(route('organisations.members.destroy', [$organisation, $member]));

        $this->assertEquals($remainingOrganisation->id, $member->fresh()->current_organisation_id);
    }
}
