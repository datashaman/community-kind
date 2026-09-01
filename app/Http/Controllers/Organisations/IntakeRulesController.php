<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Enums\IntakeUrgency;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreIntakeRulesRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class IntakeRulesController extends Controller
{
    private const FIXED_REQUIRED_FIELDS = ['party_uuid', 'program_id', 'source', 'narrative', 'presenting_needs'];

    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [OrganisationConfiguration::class, $currentOrganisation]);
        $rules = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::IntakeRules)
            ->where('configuration_key', 'default')
            ->latest('version')
            ->get();

        return Inertia::render('intake-rules/index', [
            'rules' => $rules->map(fn (OrganisationConfiguration $rule): array => [
                'id' => $rule->id,
                'version' => $rule->version,
                'status' => $rule->status->value,
                'requiredFields' => $rule->definition['required_fields'],
                'defaultUrgency' => $rule->definition['default_urgency'],
                'restrictedAccessBypassAllowed' => $rule->definition['allow_restricted_access_bypass'],
                'activatedAt' => $rule->activated_at?->toAtomString(),
                'canActivate' => $rule->status === OrganisationConfigurationStatus::Draft
                    && $rules->first()?->id === $rule->id,
            ]),
            'fixedRequiredFields' => collect(self::FIXED_REQUIRED_FIELDS)->map(fn (string $field): array => [
                'value' => $field,
                'label' => match ($field) {
                    'party_uuid' => 'Party identity',
                    'program_id' => 'Program',
                    'source' => 'Referral source',
                    'narrative' => 'Referral narrative',
                    'presenting_needs' => 'Presenting needs',
                    default => str($field)->replace('_', ' ')->title()->toString(),
                },
            ]),
            'urgencies' => collect(IntakeUrgency::cases())->map(fn (IntakeUrgency $urgency): array => [
                'value' => $urgency->value,
                'label' => str($urgency->value)->title()->toString(),
            ]),
        ]);
    }

    public function store(StoreIntakeRulesRequest $request, Organisation $currentOrganisation, CreateOrganisationConfiguration $create): RedirectResponse
    {
        Gate::authorize('create', [OrganisationConfiguration::class, $currentOrganisation]);
        $create->handle($currentOrganisation, OrganisationConfigurationArea::IntakeRules, 'default', [
            'required_fields' => [...self::FIXED_REQUIRED_FIELDS, ...$request->array('required_contact_fields')],
            'default_urgency' => $request->string('default_urgency')->toString(),
            'allow_restricted_access_bypass' => false,
        ], $request->user());

        return back();
    }

    public function activate(Organisation $currentOrganisation, string $intakeRule, ActivateOrganisationConfiguration $activate): RedirectResponse
    {
        $rule = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::IntakeRules)
            ->where('configuration_key', 'default')
            ->findOrFail($intakeRule);
        Gate::authorize('update', $rule);
        $latestId = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::IntakeRules)
            ->where('configuration_key', 'default')
            ->latest('version')
            ->value('id');
        abort_unless($rule->status === OrganisationConfigurationStatus::Draft && $rule->id === $latestId, 409);
        $activate->handle($rule, request()->user());

        return back();
    }
}
