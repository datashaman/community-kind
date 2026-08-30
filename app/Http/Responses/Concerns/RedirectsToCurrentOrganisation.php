<?php

namespace App\Http\Responses\Concerns;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

trait RedirectsToCurrentOrganisation
{
    protected function redirectPathForCurrentOrganisation(Request $request, string $redirect): string
    {
        $organisation = $this->currentOrganisation($request);

        URL::defaults(['current_organisation' => $organisation->slug]);

        return "/{$organisation->slug}{$redirect}";
    }

    protected function currentOrganisation(Request $request): Organisation
    {
        $user = $request->user();

        abort_if(! $user, 403);

        $organisation = $user->currentOrganisation;

        abort_if(! $organisation, 403);

        return $organisation;
    }
}
