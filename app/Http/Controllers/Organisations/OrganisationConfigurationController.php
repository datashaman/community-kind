<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\ActivateOrganisationConfiguration;
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
            'configurations' => OrganisationConfiguration::query()->whereNotIn('area', [OrganisationConfigurationArea::PublicForm->value, OrganisationConfigurationArea::MessageTemplate->value, OrganisationConfigurationArea::SupporterJourney->value, OrganisationConfigurationArea::IntakeRules->value])->latest()->get()->map(fn (OrganisationConfiguration $configuration): array => [
                'id' => $configuration->id,
                'area' => $configuration->area->value,
                'key' => $configuration->configuration_key,
                'version' => $configuration->version,
                'status' => $configuration->status->value,
                'definition' => $configuration->definition,
                'activatedAt' => $configuration->activated_at?->toAtomString(),
            ]),
            'areas' => collect(OrganisationConfigurationArea::cases())
                ->reject(fn (OrganisationConfigurationArea $area): bool => in_array($area, [OrganisationConfigurationArea::PublicForm, OrganisationConfigurationArea::MessageTemplate, OrganisationConfigurationArea::SupporterJourney, OrganisationConfigurationArea::IntakeRules], true))
                ->map(fn (OrganisationConfigurationArea $area): array => ['value' => $area->value, 'label' => $area->label()])
                ->values(),
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

    public function activate(Organisation $currentOrganisation, string $configuration, ActivateOrganisationConfiguration $activate): RedirectResponse
    {
        $version = OrganisationConfiguration::query()->findOrFail($configuration);
        abort_if(in_array($version->area, [OrganisationConfigurationArea::PublicForm, OrganisationConfigurationArea::MessageTemplate, OrganisationConfigurationArea::SupporterJourney, OrganisationConfigurationArea::IntakeRules], true), 404);
        Gate::authorize('update', $version);
        $activate->handle($version, request()->user());

        return back();
    }
}
