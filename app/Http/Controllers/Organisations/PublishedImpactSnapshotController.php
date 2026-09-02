<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Reporting\BuildImpactReportPack;
use App\Actions\Reporting\PublishImpactSnapshot;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Exceptions\ImpactSnapshotNotApprovable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreImpactSnapshotRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\PublishedImpactSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublishedImpactSnapshotController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [PublishedImpactSnapshot::class, $currentOrganisation]);

        /*
         * Approving a snapshot needs an active impact reporting configuration.
         * Without this the page offered the action, took the form, and failed
         * in the action with a 500 that told the person nothing they could act
         * on.
         */
        $hasActiveConfiguration = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::Reporting)
            ->where('configuration_key', 'impact')
            ->where('status', OrganisationConfigurationStatus::Active)
            ->exists();

        return Inertia::render('impact-snapshots/index', [
            'canApprove' => $hasActiveConfiguration,
            /*
             * Approving a snapshot needs ExecutiveViewer; activating the
             * configuration it depends on needs OrganisationAdministrator. The
             * person blocked here is usually not the person who can unblock it,
             * so only offer the link to someone the gate will actually admit.
             */
            'canConfigureReporting' => Gate::allows('viewAny', [OrganisationConfiguration::class, $currentOrganisation]),
            'snapshots' => PublishedImpactSnapshot::query()->latest()->get()->map(fn (PublishedImpactSnapshot $snapshot): array => ['id' => $snapshot->id, 'audience' => $snapshot->audience, 'registryVersion' => $snapshot->registry_version, 'metricCount' => count($snapshot->metrics), 'approvedAt' => $snapshot->approved_at->toAtomString(), 'publishedAt' => $snapshot->published_at?->toAtomString()]),
        ]);
    }

    public function store(StoreImpactSnapshotRequest $request, Organisation $currentOrganisation, PublishImpactSnapshot $publish): RedirectResponse
    {
        Gate::authorize('create', [PublishedImpactSnapshot::class, $currentOrganisation]);
        $validated = $request->validated();

        /*
         * The page hides the action when no configuration is active, but the
         * configuration can be retired between loading the page and submitting
         * it. Report that on the form rather than as a 500.
         */
        try {
            $publish->handle($currentOrganisation, $request->user(), (string) $validated['audience'], collect($validated)->except('audience')->all());
        } catch (ImpactSnapshotNotApprovable $exception) {
            return back()->withErrors(['audience' => $exception->getMessage()]);
        }

        return back();
    }

    public function download(Organisation $currentOrganisation, string $snapshot, BuildImpactReportPack $pack): StreamedResponse
    {
        $impactSnapshot = PublishedImpactSnapshot::query()->findOrFail($snapshot);
        Gate::authorize('view', $impactSnapshot);
        $rows = $pack->handle($impactSnapshot);

        return response()->streamDownload(function () use ($rows): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                return;
            }
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
            fclose($stream);
        }, "fictional-{$impactSnapshot->audience}-impact-pack.csv", ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
