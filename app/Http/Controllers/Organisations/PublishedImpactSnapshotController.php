<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Reporting\BuildImpactReportPack;
use App\Actions\Reporting\PublishImpactSnapshot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreImpactSnapshotRequest;
use App\Models\Organisation;
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

        return Inertia::render('impact-snapshots/index', ['snapshots' => PublishedImpactSnapshot::query()->latest()->get()->map(fn (PublishedImpactSnapshot $snapshot): array => ['id' => $snapshot->id, 'audience' => $snapshot->audience, 'registryVersion' => $snapshot->registry_version, 'metricCount' => count($snapshot->metrics), 'approvedAt' => $snapshot->approved_at->toAtomString(), 'publishedAt' => $snapshot->published_at?->toAtomString()])]);
    }

    public function store(StoreImpactSnapshotRequest $request, Organisation $currentOrganisation, PublishImpactSnapshot $publish): RedirectResponse
    {
        Gate::authorize('create', [PublishedImpactSnapshot::class, $currentOrganisation]);
        $validated = $request->validated();
        $publish->handle($currentOrganisation, $request->user(), (string) $validated['audience'], collect($validated)->except('audience')->all());

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
