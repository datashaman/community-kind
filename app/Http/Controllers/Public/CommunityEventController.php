<?php

namespace App\Http\Controllers\Public;

use App\Actions\Engagement\RegisterForCommunityEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreEventRegistrationRequest;
use App\Models\CommunityEvent;
use App\Models\Organisation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use LogicException;

class CommunityEventController extends Controller
{
    public function index(Request $request): View
    {
        return view('public.events.index', ['organisation' => $this->organisation($request), 'events' => CommunityEvent::query()->where('status', 'published')->where('registration_opens_at', '<=', now())->where('registration_closes_at', '>', now())->orderBy('starts_at')->get()]);
    }

    public function show(Request $request, string $publicOrganisation, string $event): View
    {
        return view('public.events.show', ['organisation' => $this->organisation($request), 'event' => CommunityEvent::query()->whereKey($event)->where('status', 'published')->firstOrFail()]);
    }

    public function store(StoreEventRegistrationRequest $request, string $publicOrganisation, string $event, RegisterForCommunityEvent $register): View
    {
        $organisation = $this->organisation($request);
        try {
            $registration = $register->handle($organisation, CommunityEvent::query()->findOrFail($event), ['name' => $request->string('name')->toString(), 'email' => $request->string('email')->toString(), 'consent_email' => $request->boolean('consent_email')]);
        } catch (LogicException) {
            abort(409, 'This event cannot accept that registration.');
        }

        return view('public.events.confirmation', compact('organisation', 'registration'));
    }

    private function organisation(Request $request): Organisation
    {
        $organisation = $request->attributes->get('public_organisation');
        abort_unless($organisation instanceof Organisation, 404);

        return $organisation;
    }
}
