<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Configuration\ActivateOrganisationConfiguration;
use App\Actions\Configuration\CreateOrganisationConfiguration;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\SupporterJourneyKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreMessageTemplateRequest;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
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

        return Inertia::render('message-templates/index', [
            'templates' => OrganisationConfiguration::query()
                ->where('area', OrganisationConfigurationArea::MessageTemplate)
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
                    'subject' => $template->definition['subject'] ?? '',
                    'body' => $template->definition['body'],
                    'journeyKind' => $template->definition['journey_kind'],
                    'activatedAt' => $template->activated_at?->toAtomString(),
                ]),
            'journeyKinds' => collect(SupporterJourneyKind::cases())->map(fn (SupporterJourneyKind $kind): array => [
                'value' => $kind->value,
                'label' => $kind->label(),
            ]),
        ]);
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
        $activate->handle($template, request()->user());

        return back();
    }
}
