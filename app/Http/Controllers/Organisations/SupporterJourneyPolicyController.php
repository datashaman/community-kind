<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\SupporterJourneyKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreSupporterJourneyPolicyRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupporterJourneyPolicyController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [OrganisationConfiguration::class, $currentOrganisation]);
        $policies = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::SupporterJourney)
            ->where('configuration_key', 'default')
            ->latest('version')
            ->get();

        return Inertia::render('supporter-journey-policy/index', [
            'policies' => $policies->map(fn (OrganisationConfiguration $policy): array => [
                'id' => $policy->id,
                'version' => $policy->version,
                'status' => $policy->status->value,
                'defaultKind' => $policy->definition['default_kind'],
                'defaultChannel' => $policy->definition['default_channel'],
                'defaultMessageTemplateId' => $policy->definition['default_message_template_id'] ?? null,
                'requireApproval' => $policy->definition['require_approval'],
                'dispatchRechecksConsent' => $policy->definition['dispatch_rechecks_consent'],
                'frequencyCapDays' => $policy->definition['frequency_cap_days'],
                'activatedAt' => $policy->activated_at?->toAtomString(),
                'canActivate' => $policy->status === OrganisationConfigurationStatus::Draft
                    && $policies->first()?->id === $policy->id,
            ]),
            'templates' => OrganisationConfiguration::query()
                ->where('area', OrganisationConfigurationArea::MessageTemplate)
                ->whereIn('status', [OrganisationConfigurationStatus::Active->value, OrganisationConfigurationStatus::Superseded->value])
                ->orderBy('configuration_key')
                ->orderByDesc('version')
                ->get()
                ->map(fn (OrganisationConfiguration $template): array => [
                    'id' => $template->id,
                    'key' => $template->configuration_key,
                    'name' => Str::of($template->configuration_key)->replace(['-', '_'], ' ')->title()->toString(),
                    'version' => $template->version,
                    'status' => $template->status->value,
                    'channel' => $template->definition['channel'],
                    'journeyKind' => $template->definition['journey_kind'],
                ]),
            'journeyKinds' => collect(SupporterJourneyKind::cases())->map(fn (SupporterJourneyKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ]),
            'minimumFrequencyCapDays' => (int) config('engagement.frequency_cap_days'),
        ]);
    }

    public function store(StoreSupporterJourneyPolicyRequest $request, Organisation $currentOrganisation, CreateOrganisationConfiguration $create): RedirectResponse
    {
        Gate::authorize('create', [OrganisationConfiguration::class, $currentOrganisation]);
        $create->handle($currentOrganisation, OrganisationConfigurationArea::SupporterJourney, 'default', [
            'default_kind' => $request->string('default_kind')->toString(),
            'default_channel' => $request->string('default_channel')->toString(),
            'default_message_template_id' => $request->filled('default_message_template_id') ? $request->string('default_message_template_id')->toString() : null,
            'require_approval' => true,
            'dispatch_rechecks_consent' => true,
            'frequency_cap_days' => $request->integer('frequency_cap_days'),
        ], $request->user());

        return back();
    }

    public function activate(Organisation $currentOrganisation, string $supporterJourneyPolicy, ActivateOrganisationConfiguration $activate): RedirectResponse
    {
        $policy = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::SupporterJourney)
            ->where('configuration_key', 'default')
            ->findOrFail($supporterJourneyPolicy);
        Gate::authorize('update', $policy);
        $latestId = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::SupporterJourney)
            ->where('configuration_key', 'default')
            ->latest('version')
            ->value('id');
        abort_unless($policy->status === OrganisationConfigurationStatus::Draft && $policy->id === $latestId, 409);
        $activate->handle($policy, request()->user());

        return back();
    }
}
