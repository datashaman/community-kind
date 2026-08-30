<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Organisations\ChangeOrganisationSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\UpdateOrganisationSlugRequest;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrganisationSlugController extends Controller
{
    public function update(
        UpdateOrganisationSlugRequest $request,
        Organisation $organisation,
        ChangeOrganisationSlug $changeOrganisationSlug,
    ): RedirectResponse {
        $organisation = $changeOrganisationSlug->handle($organisation, $request->validated('slug'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Organisation slug updated.')]);

        return to_route('organisations.edit', $organisation);
    }
}
