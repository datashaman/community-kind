<?php

namespace App\Http\Controllers;

use App\Models\OrganisationInvitation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $email = strtolower($request->user()->email);

        $pendingInvitations = OrganisationInvitation::query()
            ->with(['inviter', 'organisation'])
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->latest()
            ->get()
            ->map(fn (OrganisationInvitation $invitation) => [
                'id' => $invitation->id,
                'inviterName' => $invitation->inviter->name,
                'organisation' => [
                    'name' => $invitation->organisation->name,
                    'slug' => $invitation->organisation->slug,
                ],
            ]);

        return Inertia::render('dashboard', [
            'pendingInvitations' => $pendingInvitations,
        ]);
    }
}
