<?php

namespace App\Http\Controllers\Demo;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Data\Demo\PersonaGuide;
use App\Enums\OrganisationRole;
use App\Enums\TenantAuditEventType;
use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\SandboxPair;
use App\OrganisationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SandboxPersonaController extends Controller
{
    public function index(Request $request): Response
    {
        $pair = $this->pair($request);
        $organisationIds = $pair->organisations()->pluck('id');
        $roles = DB::table('role_assignments')
            ->whereIn('organisation_id', $organisationIds)
            ->whereNull('program_id')
            ->whereNull('ended_at')
            ->pluck('role', 'membership_id');
        $personas = Membership::query()
            ->with(['user', 'organisation'])
            ->whereIn('organisation_id', $organisationIds)
            ->whereNull('ended_at')
            ->orderBy('id')
            ->get()
            ->map(function (Membership $membership) use ($roles): ?array {
                $role = OrganisationRole::tryFrom((string) $roles->get($membership->id));

                if (! $role instanceof OrganisationRole) {
                    return null;
                }

                return [
                    'membershipId' => $membership->id,
                    'name' => $membership->user->name,
                    'organisation' => $membership->organisation->name,
                    'role' => $role->label(),
                    'roleKey' => $role->value,
                    ...PersonaGuide::for($role, $membership->organisation),
                ];
            })
            ->filter()
            ->unique('role')
            ->values();

        return Inertia::render('demo/personas', ['personas' => $personas]);
    }

    public function store(Request $request, RecordTenantAuditEvent $recordAudit, OrganisationContext $context): RedirectResponse
    {
        $pair = $this->pair($request);
        $validated = $request->validate(['membership_id' => ['required', 'integer']]);
        $membership = Membership::query()
            ->with(['user', 'organisation'])
            ->whereKey($validated['membership_id'])
            ->whereIn('organisation_id', $pair->organisations()->select('id'))
            ->whereNull('ended_at')
            ->firstOrFail();
        $role = OrganisationRole::tryFrom((string) DB::table('role_assignments')
            ->where('organisation_id', $membership->organisation_id)
            ->where('membership_id', $membership->id)
            ->whereNull('program_id')
            ->whereNull('ended_at')
            ->value('role'));
        abort_unless($role instanceof OrganisationRole, 404);

        Auth::login($membership->user);
        $request->session()->regenerate();
        $membership->user->switchOrganisation($membership->organisation);
        $request->session()->put([
            'demo_organisation_generation' => $membership->organisation->demo_generation,
            'auth.password_confirmed_at' => now()->getTimestamp(),
            'auth.mfa_confirmed_at' => now()->getTimestamp(),
        ]);

        $context->run($membership->organisation, fn () => $recordAudit->handle(
            $membership->organisation,
            TenantAuditEventType::DemoPersonaSelected,
            'membership',
            (string) $membership->id,
            [
                'membership_id' => $membership->id,
                'role' => $role->value,
                'generation' => $membership->organisation->demo_generation,
            ],
            $membership->user,
        ));

        return to_route('dashboard', ['current_organisation' => $membership->organisation]);
    }

    private function pair(Request $request): SandboxPair
    {
        abort_unless(config('demo_sandbox.enabled'), 404);
        $pairId = $request->session()->get('demo_sandbox_pair_id');
        abort_unless(is_string($pairId), 404);
        $pair = SandboxPair::query()->findOrFail($pairId);
        abort_unless($pair->status->isAccessible() && $pair->generation === $request->session()->get('demo_sandbox_generation'), 410);

        return $pair;
    }
}
