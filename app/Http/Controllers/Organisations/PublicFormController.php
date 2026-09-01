<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Actions\Configuration\PublicFormDefinition;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StorePublicFormRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PublicFormController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [OrganisationConfiguration::class, $currentOrganisation]);

        /*
         * Each purpose is one form; version is a revision of it. Group by
         * purpose so the page reads as an index of forms rather than a flat
         * list of every revision of every form.
         */
        $forms = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::PublicForm)
            ->orderByDesc('version')
            ->get()
            ->groupBy(fn (OrganisationConfiguration $form): string => $this->purpose($form))
            ->map(fn (Collection $versions, string $purpose): array => $this->presentForm($purpose, $versions))
            ->sortBy('purposeLabel')
            ->values()
            ->all();

        return Inertia::render('public-forms/index', [
            'purposes' => collect(PublicFormDefinition::catalogue())->map(fn (array $purpose, string $value): array => [
                'value' => $value,
                'label' => $purpose['label'],
                'description' => $purpose['description'],
                'fields' => collect($purpose['fields'])->map(fn (array $field): array => [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'required' => $field['fixed_required'],
                    'fixedRequired' => $field['fixed_required'],
                ])->all(),
            ])->values(),
            'forms' => $forms,
        ]);
    }

    /**
     * @param  Collection<int, OrganisationConfiguration>  $versions  Newest first.
     * @return array<string, mixed>
     */
    private function presentForm(string $purpose, Collection $versions): array
    {
        $highestVersion = $versions->max('version');

        return [
            'purpose' => $purpose,
            /*
             * A row whose stored purpose is no longer in the catalogue falls
             * back to its configuration key, which has no catalogue entry.
             * Indexing the catalogue directly used to fail the whole page.
             */
            'purposeLabel' => PublicFormDefinition::catalogue()[$purpose]['label'] ?? $purpose,
            'activeVersion' => $versions->firstWhere('status', OrganisationConfigurationStatus::Active)?->version,
            'versions' => $versions->map(fn (OrganisationConfiguration $form): array => [
                'id' => $form->id,
                'version' => $form->version,
                'status' => $form->status->value,
                'fields' => PublicFormDefinition::displayFields($purpose, $form->definition),
                'activatedAt' => $form->activated_at?->toAtomString(),
                'canActivate' => $form->status === OrganisationConfigurationStatus::Draft
                    && $highestVersion === $form->version,
            ])->values()->all(),
        ];
    }

    public function store(StorePublicFormRequest $request, Organisation $currentOrganisation, CreateOrganisationConfiguration $create): RedirectResponse
    {
        Gate::authorize('create', [OrganisationConfiguration::class, $currentOrganisation]);
        $purpose = $request->string('form')->toString();
        $orderedFields = array_values(array_filter($request->array('ordered_fields'), is_string(...)));
        $requiredFields = array_values(array_filter($request->array('required_fields'), is_string(...)));
        $create->handle(
            $currentOrganisation,
            OrganisationConfigurationArea::PublicForm,
            $purpose,
            PublicFormDefinition::build($purpose, $orderedFields, $requiredFields),
            $request->user(),
        );

        return back();
    }

    public function activate(Organisation $currentOrganisation, string $publicForm, ActivateOrganisationConfiguration $activate): RedirectResponse
    {
        $form = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::PublicForm)
            ->findOrFail($publicForm);
        Gate::authorize('update', $form);
        $latestId = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::PublicForm)
            ->where('configuration_key', $form->configuration_key)
            ->latest('version')
            ->value('id');
        abort_unless($form->status === OrganisationConfigurationStatus::Draft && $form->id === $latestId, 409);
        $activate->handle($form, request()->user());

        return back();
    }

    private function purpose(OrganisationConfiguration $form): string
    {
        $purpose = $form->definition['form'] ?? null;

        return is_string($purpose) && in_array($purpose, PublicFormDefinition::purposes(), true)
            ? $purpose
            : $form->configuration_key;
    }
}
