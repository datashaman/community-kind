<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Enums\OrganisationConfigurationArea;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreOrganisationConfigurationRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;

class OrganisationConfigurationController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [OrganisationConfiguration::class, $currentOrganisation]);

        return Inertia::render('organisation-configurations/index', [
            'configurations' => [],
            'areas' => [],
        ]);
    }

    /** @throws JsonException */
    public function store(StoreOrganisationConfigurationRequest $request, Organisation $currentOrganisation, CreateOrganisationConfiguration $create): RedirectResponse
    {
        Gate::authorize('create', [OrganisationConfiguration::class, $currentOrganisation]);
        $definition = json_decode($request->string('definition_json')->toString(), true, flags: JSON_THROW_ON_ERROR);
        abort_unless(is_array($definition), 422);
        try {
            $create->handle($currentOrganisation, OrganisationConfigurationArea::from($request->string('area')->toString()), $request->string('configuration_key')->toString(), $definition, $request->user());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'definition_json' => collect($exception->errors())->flatten()->implode(' '),
            ]);
        }

        return back();
    }

    public function activate(): never
    {
        abort(404);
    }
}
