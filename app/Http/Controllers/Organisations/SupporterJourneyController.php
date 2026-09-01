<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Engagement\ApproveSupporterJourney;
use App\Actions\Engagement\DispatchSupporterJourney;
use App\Actions\Engagement\EvaluateAudienceSegment;
use App\Actions\Engagement\TransitionSupporterJourney;
use App\Actions\Engagement\TransitionSupporterJourneyRecipient;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\SupporterJourneyEventType;
use App\Enums\SupporterJourneyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreSupporterJourneyRequest;
use App\Http\Requests\Organisations\TransitionSupporterJourneyRecipientRequest;
use App\Http\Requests\Organisations\TransitionSupporterJourneyRequest;
use App\Models\AudienceSegment;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\SupporterJourney;
use App\Models\SupporterJourneyEvent;
use App\Models\SupporterJourneyRecipient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupporterJourneyController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [SupporterJourney::class, $currentOrganisation]);

        return Inertia::render('supporter-journeys/index', [
            'journeys' => SupporterJourney::query()->withCount('recipients')->latest()->get()->map(fn (SupporterJourney $journey): array => [
                'id' => $journey->id,
                'name' => $journey->name,
                'status' => $journey->status->value,
                'recipientCount' => $journey->recipients_count,
            ]),
            'segments' => AudienceSegment::query()->orderBy('name')->get(['id', 'name']),
            'templates' => OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::MessageTemplate)->where('status', OrganisationConfigurationStatus::Active)->orderBy('configuration_key')->get()->map(fn (OrganisationConfiguration $configuration): array => ['key' => $configuration->configuration_key, 'channel' => $configuration->definition['channel'], 'journeyKind' => $configuration->definition['journey_kind']]),
        ]);
    }

    public function store(StoreSupporterJourneyRequest $request, Organisation $currentOrganisation): RedirectResponse
    {
        Gate::authorize('create', [SupporterJourney::class, $currentOrganisation]);
        $journey = SupporterJourney::query()->create([
            'organisation_id' => $currentOrganisation->id,
            'audience_segment_id' => $request->validated('audience_segment_id'),
            'name' => $request->validated('name'),
            'journey_kind' => $request->validated('journey_kind'),
            'channel' => $request->validated('channel'),
            'subject' => (string) $request->validated('subject'),
            'body' => $request->validated('body'),
            'status' => SupporterJourneyStatus::Draft,
            'version' => 1,
            'experiment' => $request->boolean('experiment_enabled') ? ['subject' => $request->validated('variant_subject'), 'body' => $request->validated('variant_body')] : null,
            'created_by_user_id' => $request->user()->id,
        ]);

        return to_route('supporter-journeys.show', [$currentOrganisation, $journey]);
    }

    public function show(Organisation $currentOrganisation, string $supporterJourney, EvaluateAudienceSegment $evaluate): Response
    {
        $journey = SupporterJourney::query()->with(['audienceSegment', 'recipients.party', 'recipients.events'])->findOrFail($supporterJourney);
        Gate::authorize('view', $journey);
        $previewAudience = $journey->audience_snapshot ?? $evaluate->handle($journey->audienceSegment)
            ->map(fn (array $supporter): array => [
                'uuid' => $supporter['uuid'],
                'displayName' => $supporter['displayName'],
                'donationCount' => $supporter['donationCount'],
                'activityFrequency' => $supporter['activityFrequency'],
                'activityValue' => $supporter['activityValue'],
            ])->values()->all();

        return Inertia::render('supporter-journeys/show', [
            'journey' => [
                'id' => $journey->id,
                'name' => $journey->name,
                'subject' => $journey->subject,
                'body' => $journey->body,
                'status' => $journey->status->value,
                'kind' => $journey->journey_kind->value,
                'channel' => $journey->channel,
                'scheduledFor' => $journey->scheduled_for?->toAtomString(),
                'pausedAt' => $journey->paused_at?->toAtomString(),
                'experiment' => $journey->experiment,
                'audienceName' => $journey->audienceSegment->name,
                'audienceSnapshot' => $previewAudience,
                'approvalHash' => $journey->approval_hash,
                'recipients' => $journey->recipients->map(fn (SupporterJourneyRecipient $recipient): array => [
                    'id' => $recipient->id,
                    'displayName' => $recipient->party->display_name,
                    'status' => $recipient->status->value,
                    'variant' => $recipient->variant,
                    'attemptCount' => $recipient->attempt_count,
                    'events' => $recipient->events->map(fn (SupporterJourneyEvent $event): array => ['id' => $event->id, 'type' => $event->type->value]),
                    'actionKeys' => collect(SupporterJourneyEventType::cases())->mapWithKeys(fn (SupporterJourneyEventType $type): array => [$type->value => Str::uuid()->toString()]),
                ]),
            ],
            'simulationOnly' => config('engagement.simulation_only') && app()->environment(['local', 'testing']),
        ]);
    }

    public function approve(Organisation $currentOrganisation, string $supporterJourney, ApproveSupporterJourney $approve): RedirectResponse
    {
        $journey = SupporterJourney::query()->findOrFail($supporterJourney);
        Gate::authorize('update', $journey);
        $approve->handle($journey, request()->user());

        return back();
    }

    public function dispatch(Organisation $currentOrganisation, string $supporterJourney, DispatchSupporterJourney $dispatch): RedirectResponse
    {
        $journey = SupporterJourney::query()->findOrFail($supporterJourney);
        Gate::authorize('update', $journey);
        $dispatch->handle($journey);

        return back();
    }

    public function transitionJourney(TransitionSupporterJourneyRequest $request, Organisation $currentOrganisation, string $supporterJourney, TransitionSupporterJourney $transition): RedirectResponse
    {
        $journey = SupporterJourney::query()->findOrFail($supporterJourney);
        Gate::authorize('update', $journey);
        $scheduledFor = $request->validated('scheduled_for');
        $transition->handle($journey, SupporterJourneyStatus::from($request->string('status')->toString()), is_string($scheduledFor) ? $scheduledFor : null, $request->user());

        return back();
    }

    public function transition(TransitionSupporterJourneyRecipientRequest $request, Organisation $currentOrganisation, string $supporterJourney, string $recipient, TransitionSupporterJourneyRecipient $transition): RedirectResponse
    {
        $journey = SupporterJourney::query()->findOrFail($supporterJourney);
        Gate::authorize('update', $journey);
        $journeyRecipient = SupporterJourneyRecipient::query()->where('supporter_journey_id', $journey->id)->findOrFail($recipient);
        $transition->handle($journeyRecipient, SupporterJourneyEventType::from($request->validated('type')), $request->validated('idempotency_key'), $request->user());

        return back();
    }
}
