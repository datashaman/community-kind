<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Organisations\AcceptOrganisationInvitation;
use App\Actions\Organisations\IssueOrganisationInvitation;
use App\Enums\OrganisationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\CreateOrganisationInvitationRequest;
use App\Http\Requests\Organisations\RespondToOrganisationInvitationRequest;
use App\Models\Organisation;
use App\Models\OrganisationInvitation;
use App\Models\User;
use App\Notifications\Organisations\OrganisationInvitation as OrganisationInvitationNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class OrganisationInvitationController extends Controller
{
    /**
     * Store a newly created invitation.
     */
    public function store(CreateOrganisationInvitationRequest $request, Organisation $organisation, IssueOrganisationInvitation $issueInvitation): RedirectResponse
    {
        Gate::authorize('inviteMember', $organisation);

        $issuedInvitation = $issueInvitation->handle(
            $organisation,
            $request->user(),
            $request->validated('email'),
            OrganisationRole::from($request->validated('role')),
        );

        Notification::route('mail', $issuedInvitation->invitation->email)
            ->notify(new OrganisationInvitationNotification(
                $issuedInvitation->invitation,
                $issuedInvitation->token,
                User::query()->where('email', $issuedInvitation->invitation->email)->exists(),
            ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }

    /**
     * Cancel the specified invitation.
     */
    public function destroy(Request $request, Organisation $organisation, OrganisationInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->organisation_id === $organisation->id, 404);

        Gate::authorize('cancelInvitation', $organisation);

        $invitation->update([
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('organisations.edit', ['organisation' => $organisation->slug]);
    }

    /**
     * Accept the invitation.
     */
    public function accept(RespondToOrganisationInvitationRequest $request, OrganisationInvitation $invitation, AcceptOrganisationInvitation $acceptInvitation): RedirectResponse
    {
        $acceptInvitation->handle($request->user(), $invitation);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation accepted.')]);

        return to_route('dashboard');
    }

    /**
     * Decline the invitation.
     */
    public function decline(RespondToOrganisationInvitationRequest $request, OrganisationInvitation $invitation): RedirectResponse
    {
        $invitation->update([
            'revoked_at' => now(),
            'revoked_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation declined.')]);

        return to_route('dashboard');
    }
}
