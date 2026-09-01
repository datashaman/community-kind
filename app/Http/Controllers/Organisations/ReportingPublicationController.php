<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreReportingPublicationRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Reporting\MetricRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ReportingPublicationController extends Controller
{
    public function index(Organisation $currentOrganisation, MetricRegistry $metrics): Response
    {
        Gate::authorize('viewAny', [OrganisationConfiguration::class, $currentOrganisation]);
        $catalogue = collect($metrics->all())->keyBy('id');
        $versions = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::Reporting)
            ->where('configuration_key', 'impact')
            ->latest('version')
            ->get();

        return Inertia::render('reporting-publication/index', [
            'metrics' => $catalogue->values(),
            'versions' => $versions->map(function (OrganisationConfiguration $version) use ($catalogue, $versions): array {
                $publicMetrics = $this->selectedMetrics($version->definition['public_metric_ids'] ?? [], $catalogue);
                $packMetrics = $this->selectedMetrics($version->definition['pack_metric_ids'] ?? [], $catalogue);
                $hasUnavailableMetrics = collect([...$publicMetrics, ...$packMetrics])->contains('available', false);

                return [
                    'id' => $version->id,
                    'version' => $version->version,
                    'status' => $version->status->value,
                    'publicMetrics' => $publicMetrics,
                    'packMetrics' => $packMetrics,
                    'activatedAt' => $version->activated_at?->toAtomString(),
                    'hasUnavailableMetrics' => $hasUnavailableMetrics,
                    'canActivate' => $version->status === OrganisationConfigurationStatus::Draft
                        && $versions->first()?->id === $version->id
                        && ! $hasUnavailableMetrics,
                ];
            }),
        ]);
    }

    public function store(StoreReportingPublicationRequest $request, Organisation $currentOrganisation, CreateOrganisationConfiguration $create): RedirectResponse
    {
        Gate::authorize('create', [OrganisationConfiguration::class, $currentOrganisation]);
        $create->handle($currentOrganisation, OrganisationConfigurationArea::Reporting, 'impact', [
            'public_metric_ids' => array_values(array_filter($request->array('public_metric_ids'), is_string(...))),
            'pack_metric_ids' => array_values(array_filter($request->array('pack_metric_ids'), is_string(...))),
        ], $request->user());

        return back();
    }

    public function activate(Organisation $currentOrganisation, string $reportingPublication, ActivateOrganisationConfiguration $activate, MetricRegistry $metrics): RedirectResponse
    {
        $version = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::Reporting)
            ->where('configuration_key', 'impact')
            ->findOrFail($reportingPublication);
        Gate::authorize('update', $version);
        $latestId = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::Reporting)
            ->where('configuration_key', 'impact')
            ->latest('version')
            ->value('id');
        abort_unless($version->status === OrganisationConfigurationStatus::Draft && $version->id === $latestId, 409);
        $configuredIds = [...($version->definition['public_metric_ids'] ?? []), ...($version->definition['pack_metric_ids'] ?? [])];
        abort_if(collect($configuredIds)->diff($metrics->ids())->isNotEmpty(), 409);
        $activate->handle($version, request()->user());

        return back();
    }

    /**
     * @param  Collection<string, array{id: string, version: string, category: string, domain: string, label: string, description: string, formula: string, unit: string, dimensions: list<string>}>  $catalogue
     * @return list<array{id: string, label: string, domain: string|null, unit: string|null, available: bool}>
     */
    private function selectedMetrics(mixed $selectedIds, Collection $catalogue): array
    {
        if (! is_array($selectedIds)) {
            return [];
        }

        $selectedMetrics = [];
        foreach ($selectedIds as $id) {
            if (! is_string($id)) {
                continue;
            }
            $metric = $catalogue->get($id);
            $selectedMetrics[] = [
                'id' => $id,
                'label' => $metric['label'] ?? $id,
                'domain' => $metric['domain'] ?? null,
                'unit' => $metric['unit'] ?? null,
                'available' => $metric !== null,
            ];
        }

        return $selectedMetrics;
    }
}
