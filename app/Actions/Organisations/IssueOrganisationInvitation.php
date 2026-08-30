<?php

namespace App\Actions\Organisations;

use App\Data\IssuedOrganisationInvitation;
use App\Enums\OrganisationPermission;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IssueOrganisationInvitation
{
    /**
     * @param  array<int, array{role: string, program_id: int|null}>  $roleAssignments
     */
    public function handle(
        Organisation $organisation,
        User $inviter,
        string $email,
        int|OrganisationRole|null $personPartyId = null,
        ?string $newPersonName = null,
        array $roleAssignments = [],
        bool $offersOwnership = false,
    ): IssuedOrganisationInvitation {
        if (! $inviter->hasOrganisationPermission($organisation, OrganisationPermission::CreateInvitation)) {
            throw ValidationException::withMessages([
                'invitation' => __('You are not allowed to invite members to this organisation.'),
            ]);
        }

        if ($personPartyId instanceof OrganisationRole) {
            $roleAssignments = [['role' => $personPartyId->value, 'program_id' => null]];
            $personPartyId = null;
            $newPersonName = $email;
        }

        if ($roleAssignments === []) {
            throw ValidationException::withMessages(['role_assignments' => __('At least one operational role is required.')]);
        }

        $assignmentKeys = collect($roleAssignments)->map(
            fn (array $assignment) => $assignment['role'].':'.($assignment['program_id'] ?? 'organisation'),
        );

        if ($assignmentKeys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'role_assignments' => __('Each role and scope combination may only be proposed once.'),
            ]);
        }

        $includesAdministrator = collect($roleAssignments)->contains(
            fn (array $assignment) => $assignment['role'] === OrganisationRole::OrganisationAdministrator->value,
        );

        if (($offersOwnership || $includesAdministrator) && ! $inviter->ownsOrganisation($organisation)) {
            throw ValidationException::withMessages([
                'role_assignments' => __('Only an organisation owner can appoint another owner or organisation administrator.'),
            ]);
        }

        return DB::transaction(fn () => app(OrganisationContext::class)->run($organisation, function () use (
            $organisation,
            $inviter,
            $email,
            $personPartyId,
            $newPersonName,
            $roleAssignments,
            $offersOwnership,
        ): IssuedOrganisationInvitation {
            if ($personPartyId !== null) {
                Party::query()->whereKey($personPartyId)->where('kind', 'person')->firstOrFail();
            } elseif (blank($newPersonName)) {
                throw ValidationException::withMessages(['new_person_name' => __('A new person Party name is required.')]);
            }

            $token = Str::random(64);
            $firstRole = OrganisationRole::from($roleAssignments[0]['role']);

            $invitation = $organisation->invitations()->create([
                'token_hash' => hash('sha256', $token),
                'email' => Str::lower($email),
                'person_party_id' => $personPartyId,
                'new_person_name' => $newPersonName,
                'role' => $firstRole,
                'offers_ownership' => $offersOwnership,
                'invited_by' => $inviter->id,
                'expires_at' => now()->addHours(72),
            ]);

            $invitation->roleAssignments()->createMany(
                collect($roleAssignments)->map(fn (array $assignment) => [
                    'organisation_id' => $organisation->id,
                    'role' => OrganisationRole::from($assignment['role']),
                    'program_id' => $assignment['program_id'] ?? null,
                ])->all(),
            );

            return new IssuedOrganisationInvitation($invitation, $token);
        }));
    }
}
