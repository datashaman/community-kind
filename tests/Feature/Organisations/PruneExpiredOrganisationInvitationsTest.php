<?php

namespace Tests\Feature\Organisations;

use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneExpiredOrganisationInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_invitations_are_deleted_by_the_scheduled_cleanup(): void
    {
        $this->travelTo(now()->startOfDay());

        $owner = User::factory()->create();
        $organisation = Organisation::factory()->create();

        $organisation->members()->attach($owner, ['is_owner' => true]);

        $expiredInvitation = OrganisationInvitation::factory()->expired()->create([
            'organisation_id' => $organisation->id,
            'invited_by' => $owner->id,
        ]);

        $unexpiredInvitation = OrganisationInvitation::factory()->expiresIn(1)->create([
            'organisation_id' => $organisation->id,
            'invited_by' => $owner->id,
        ]);

        $invitationWithoutExpiration = OrganisationInvitation::factory()->create([
            'organisation_id' => $organisation->id,
            'invited_by' => $owner->id,
        ]);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertDatabaseMissing('organisation_invitations', [
            'id' => $expiredInvitation->id,
        ]);

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $unexpiredInvitation->id,
        ]);

        $this->assertDatabaseHas('organisation_invitations', [
            'id' => $invitationWithoutExpiration->id,
        ]);
    }
}
