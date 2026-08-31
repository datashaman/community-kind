<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrganisationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $organisation = $request->attributes->get('public_organisation');

        abort_unless($organisation instanceof Organisation, 404);

        return view('public.organisation', [
            'organisationName' => $organisation->name,
            'canonicalUrl' => route('public.organisations.show', [
                'public_organisation' => $organisation->slug,
            ]),
            'sourceUrl' => rtrim((string) config('app.url'), '/').'/source-and-licence',
            'donationUrl' => route('public.donations.create', ['public_organisation' => $organisation->slug]),
            'volunteerUrl' => route('public.volunteers.index', ['public_organisation' => $organisation->slug]),
        ]);
    }
}
