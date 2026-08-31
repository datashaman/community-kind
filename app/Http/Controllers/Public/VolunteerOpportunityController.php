<?php

namespace App\Http\Controllers\Public;

use App\Actions\Volunteering\RegisterVolunteerApplication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreVolunteerApplicationRequest;
use App\Models\Organisation;
use App\Models\VolunteerOpportunity;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use LogicException;

class VolunteerOpportunityController extends Controller
{
    public function index(Request $request): View
    {
        $organisation = $this->organisation($request);

        return view('public.volunteers.index', ['organisation' => $organisation, 'opportunities' => VolunteerOpportunity::query()->where('status', 'published')->where('registration_opens_at', '<=', now())->where('registration_closes_at', '>', now())->orderBy('starts_at')->get()]);
    }

    public function show(Request $request, string $publicOrganisation, string $opportunity): View
    {
        $organisation = $this->organisation($request);
        $opportunity = VolunteerOpportunity::query()->whereKey($opportunity)->where('status', 'published')->firstOrFail();

        return view('public.volunteers.show', compact('organisation', 'opportunity'));
    }

    public function store(StoreVolunteerApplicationRequest $request, string $publicOrganisation, string $opportunity, RegisterVolunteerApplication $register): View
    {
        $organisation = $this->organisation($request);
        $opportunity = VolunteerOpportunity::query()->whereKey($opportunity)->firstOrFail();
        $validated = $request->validated();
        try {
            $application = $register->handle($organisation, $opportunity, [
                'name' => (string) $validated['name'],
                'email' => (string) $validated['email'],
                'interests' => array_values(array_map(strval(...), $validated['interests'] ?? [])),
                'availability' => array_values(array_map(strval(...), $validated['availability'])),
                'consent_email' => (bool) $validated['consent_email'],
            ]);
        } catch (LogicException) {
            abort(409, 'This opportunity cannot accept that registration.');
        }

        return view('public.volunteers.confirmation', compact('organisation', 'application'));
    }

    private function organisation(Request $request): Organisation
    {
        $organisation = $request->attributes->get('public_organisation');
        abort_unless($organisation instanceof Organisation, 404);

        return $organisation;
    }
}
