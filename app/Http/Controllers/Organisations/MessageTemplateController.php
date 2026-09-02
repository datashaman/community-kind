<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Actions\Configuration\RetireOrganisationConfiguration;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\SupporterJourneyKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreMessageTemplateRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplateController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [OrganisationConfiguration::class, $currentOrganisation]);
        $showRetired = request()->boolean('retired');

        /*
         * Each configuration_key is one template; version is a revision of it.
         * Group by key so the page reads as an index of templates rather than a
         * flat list of every revision of every template.
         */
        $templates = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::MessageTemplate)
            ->orderBy('configuration_key')
            ->orderByDesc('version')
            ->get()
            ->groupBy('configuration_key')
            ->map(fn (Collection $versions, string $key): array => $this->presentTemplate($key, $versions))
            ->values();

        $retiredCount = $templates->where('retired', true)->count();

        return Inertia::render('message-templates/index', [
            'templates' => $showRetired
                ? $templates->all()
                : $templates->where('retired', false)->values()->all(),
            'retiredCount' => $retiredCount,
            'showRetired' => $showRetired,
            'journeyKinds' => collect(SupporterJourneyKind::cases())->map(fn (SupporterJourneyKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ]),
        ]);
    }

    /**
     * @param  Collection<int, OrganisationConfiguration>  $versions  Newest first.
     * @return array<string, mixed>
     */
    private function presentTemplate(string $key, Collection $versions): array
    {
        $latest = $versions->firstOrFail();
        $highestVersion = $versions->max('version');

        return [
            'key' => $key,
            'name' => Str::of($key)->replace(['-', '_'], ' ')->title()->toString(),
            'channel' => $latest->definition['channel'],
            'journeyKind' => $latest->definition['journey_kind'],
            'retired' => $latest->status === OrganisationConfigurationStatus::Retired,
            'activeVersion' => $versions->firstWhere('status', OrganisationConfigurationStatus::Active)?->version,
            'versions' => $versions->map(fn (OrganisationConfiguration $template): array => [
                'id' => $template->id,
                'version' => $template->version,
                'status' => $template->status->value,
                'channel' => $template->definition['channel'],
                'subject' => $template->definition['subject'] ?? '',
                'body' => $template->definition['body'],
                'activatedAt' => $template->activated_at?->toAtomString(),
                'canActivate' => $template->status === OrganisationConfigurationStatus::Draft
                    && $highestVersion === $template->version,
            ])->values()->all(),
        ];
    }

    public function store(StoreMessageTemplateRequest $request, Organisation $currentOrganisation, CreateOrganisationConfiguration $create): RedirectResponse
    {
        Gate::authorize('create', [OrganisationConfiguration::class, $currentOrganisation]);
        $key = $request->filled('template_key')
            ? $request->string('template_key')->toString()
            : Str::of($request->string('name')->toString())->ascii()->slug()->limit(100, '')->toString();
        $channel = $request->string('channel')->toString();
        $create->handle($currentOrganisation, OrganisationConfigurationArea::MessageTemplate, $key, [
            'channel' => $channel,
            'subject' => $channel === 'email' ? $request->string('subject')->toString() : null,
            'body' => $request->string('body')->toString(),
            'journey_kind' => $request->string('journey_kind')->toString(),
        ], $request->user());

        return back();
    }

    public function activate(Organisation $currentOrganisation, string $messageTemplate, ActivateOrganisationConfiguration $activate): RedirectResponse
    {
        $template = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::MessageTemplate)
            ->findOrFail($messageTemplate);
        Gate::authorize('update', $template);
        $latestId = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::MessageTemplate)
            ->where('configuration_key', $template->configuration_key)
            ->latest('version')
            ->value('id');
        abort_unless($template->status === OrganisationConfigurationStatus::Draft && $template->id === $latestId, 409);
        $activate->handle($template, request()->user());

        return back();
    }

    public function retire(Organisation $currentOrganisation, string $templateKey, RetireOrganisationConfiguration $retire): RedirectResponse
    {
        $latest = OrganisationConfiguration::query()
            ->where('area', OrganisationConfigurationArea::MessageTemplate)
            ->where('configuration_key', $templateKey)
            ->latest('version')
            ->firstOrFail();
        Gate::authorize('retire', $latest);
        abort_if($latest->status === OrganisationConfigurationStatus::Retired, 409);
        $retire->handle($currentOrganisation, OrganisationConfigurationArea::MessageTemplate, $templateKey, request()->user());

        return back();
    }
}
