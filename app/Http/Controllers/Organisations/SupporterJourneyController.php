<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Engagement\ApproveSupporterJourney;
use App\Actions\Engagement\DispatchSupporterJourney;
use App\Actions\Engagement\EvaluateAudienceSegment;
use App\Actions\Engagement\TransitionSupporterJourneyRecipient;
use App\Enums\SupporterJourneyEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreSupporterJourneyRequest;
use App\Http\Requests\Organisations\TransitionSupporterJourneyRecipientRequest;
use App\Models\AudienceSegment;
use App\Models\Organisation;
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
        ]);
    }

    public function store(StoreSupporterJourneyRequest $request, Organisation $currentOrganisation): RedirectResponse
    {
        Gate::authorize('create', [SupporterJourney::class, $currentOrganisation]);
        $journey = SupporterJourney::query()->create([
            'organisation_id' => $currentOrganisation->id,
            ...$request->validated(),
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
            ])->values()->all();

        return Inertia::render('supporter-journeys/show', [
            'journey' => [
                'id' => $journey->id,
                'name' => $journey->name,
                'subject' => $journey->subject,
                'body' => $journey->body,
                'status' => $journey->status->value,
                'audienceName' => $journey->audienceSegment->name,
                'audienceSnapshot' => $previewAudience,
                'approvalHash' => $journey->approval_hash,
                'recipients' => $journey->recipients->map(fn (SupporterJourneyRecipient $recipient): array => [
                    'id' => $recipient->id,
                    'displayName' => $recipient->party->display_name,
                    'status' => $recipient->status->value,
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

    public function transition(TransitionSupporterJourneyRecipientRequest $request, Organisation $currentOrganisation, string $supporterJourney, string $recipient, TransitionSupporterJourneyRecipient $transition): RedirectResponse
    {
        $journey = SupporterJourney::query()->findOrFail($supporterJourney);
        Gate::authorize('update', $journey);
        $journeyRecipient = SupporterJourneyRecipient::query()->where('supporter_journey_id', $journey->id)->findOrFail($recipient);
        $transition->handle($journeyRecipient, SupporterJourneyEventType::from($request->validated('type')), $request->validated('idempotency_key'), $request->user());

        return back();
    }
}
