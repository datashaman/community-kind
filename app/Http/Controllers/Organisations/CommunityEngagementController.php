<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Engagement\CreatePartnerProfile;
use App\Actions\Engagement\RecordPartnerCommitment;
use App\Actions\Engagement\TransitionEventRegistration;
use App\Actions\Engagement\TransitionInKindOffer;
use App\Enums\CommunityEventStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\InKindOfferStatus;
use App\Enums\PartnerCommitmentStatus;
use App\Enums\PartyContactType;
use App\Enums\TenantAuditEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreCommunityEventRequest;
use App\Http\Requests\Organisations\StorePartnerCommitmentRequest;
use App\Http\Requests\Organisations\StorePartnerProfileRequest;
use App\Http\Requests\Organisations\TransitionEventRegistrationRequest;
use App\Http\Requests\Organisations\TransitionInKindOfferRequest;
use App\Models\CommunityEvent;
use App\Models\EventRegistration;
use App\Models\InKindOffer;
use App\Models\Organisation;
use App\Models\PartnerCommitment;
use App\Models\PartnerProfile;
use App\Models\PartyContactPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommunityEngagementController extends Controller
{
    public function index(Request $request, Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [CommunityEvent::class, $currentOrganisation]);
        $events = CommunityEvent::query()->with('registrations.party')->orderByDesc('starts_at')->get()->map(fn (CommunityEvent $event): array => ['id' => $event->id, 'title' => $event->title, 'status' => $event->status->value, 'capacity' => $event->capacity, 'startsAt' => $event->starts_at->toAtomString(), 'registrations' => $event->registrations->map(fn (EventRegistration $registration): array => ['id' => $registration->id, 'name' => $registration->party->display_name, 'status' => $registration->status->value, 'remindedAt' => $registration->reminded_at?->toAtomString(), 'allowedTransitions' => collect($registration->status->allowedTransitions())->map(fn (EventRegistrationStatus $status): string => $status->value)->all()])->values()->all()]);
        $offers = InKindOffer::query()->with('party')->latest('offered_at')->get()->map(fn (InKindOffer $offer): array => ['id' => $offer->id, 'name' => $offer->party->display_name, 'category' => $offer->category, 'description' => $offer->description, 'quantity' => $offer->quantity, 'unit' => $offer->unit, 'estimatedValueMinor' => $offer->estimated_value_minor, 'currency' => $offer->currency, 'condition' => $offer->condition, 'status' => $offer->status->value, 'outcome' => $offer->fulfilment_outcome, 'allowedTransitions' => collect($offer->status->allowedTransitions())->map(fn (InKindOfferStatus $status): string => $status->value)->all()]);
        $partners = PartnerProfile::query()->with(['party.contactPoints', 'commitments'])->latest('engaged_at')->get()->map(fn (PartnerProfile $profile): array => ['id' => $profile->id, 'name' => $profile->party->display_name, 'type' => $profile->partner_type, 'status' => $profile->status->value, 'relationshipSummary' => $profile->relationship_summary, 'email' => $profile->party->contactPoints->first(fn (PartyContactPoint $contact): bool => $contact->type === PartyContactType::Email)?->encrypted_value->reveal(), 'commitments' => $profile->commitments->map(fn (PartnerCommitment $commitment): array => ['id' => $commitment->id, 'title' => $commitment->title, 'details' => $commitment->details, 'status' => $commitment->status->value, 'dueOn' => $commitment->due_on?->toDateString()])->values()->all()]);

        return Inertia::render('community-engagement/index', compact('events', 'offers', 'partners'));
    }

    public function storeEvent(StoreCommunityEventRequest $request, Organisation $currentOrganisation, RecordTenantAuditEvent $recordAudit): RedirectResponse
    {
        Gate::authorize('create', [CommunityEvent::class, $currentOrganisation]);
        $status = CommunityEventStatus::from($request->string('status')->toString());
        $event = CommunityEvent::query()->create([...$request->validated(), 'status' => $status, 'published_at' => $status === CommunityEventStatus::Published ? now() : null, 'created_by_user_id' => $request->user()->id]);
        $recordAudit->handle($currentOrganisation, TenantAuditEventType::CommunityEventCreated, 'community_event', $event->id, ['event_id' => $event->id, 'capacity' => $event->capacity], $request->user());

        return back();
    }

    public function transitionRegistration(TransitionEventRegistrationRequest $request, Organisation $currentOrganisation, string $registration, TransitionEventRegistration $transition): RedirectResponse
    {
        Gate::authorize('viewAny', [CommunityEvent::class, $currentOrganisation]);
        $transition->handle(EventRegistration::query()->findOrFail($registration), EventRegistrationStatus::from($request->string('status')->toString()), $request->user());

        return back();
    }

    public function remindRegistration(Request $request, Organisation $currentOrganisation, string $registration, TransitionEventRegistration $transition): RedirectResponse
    {
        Gate::authorize('viewAny', [CommunityEvent::class, $currentOrganisation]);
        $transition->remind(EventRegistration::query()->findOrFail($registration), $request->user());

        return back();
    }

    public function transitionOffer(TransitionInKindOfferRequest $request, Organisation $currentOrganisation, string $offer, TransitionInKindOffer $transition): RedirectResponse
    {
        Gate::authorize('viewAny', [CommunityEvent::class, $currentOrganisation]);
        $transition->handle(InKindOffer::query()->findOrFail($offer), InKindOfferStatus::from($request->string('status')->toString()), $request->string('fulfilment_outcome')->toString() ?: null, $request->user());

        return back();
    }

    public function storePartner(StorePartnerProfileRequest $request, Organisation $currentOrganisation, CreatePartnerProfile $create): RedirectResponse
    {
        Gate::authorize('viewAny', [CommunityEvent::class, $currentOrganisation]);
        $validated = $request->validated();
        $create->handle($currentOrganisation, ['name' => (string) $validated['name'], 'email' => isset($validated['email']) ? (string) $validated['email'] : null, 'telephone' => isset($validated['telephone']) ? (string) $validated['telephone'] : null, 'partner_type' => (string) $validated['partner_type'], 'relationship_summary' => (string) $validated['relationship_summary']], $request->user());

        return back();
    }

    public function storeCommitment(StorePartnerCommitmentRequest $request, Organisation $currentOrganisation, string $partner, RecordPartnerCommitment $record): RedirectResponse
    {
        Gate::authorize('viewAny', [CommunityEvent::class, $currentOrganisation]);
        $validated = $request->validated();
        $record->handle(PartnerProfile::query()->findOrFail($partner), ['title' => (string) $validated['title'], 'details' => (string) $validated['details'], 'status' => PartnerCommitmentStatus::from((string) $validated['status']), 'due_on' => isset($validated['due_on']) ? (string) $validated['due_on'] : null], $request->user());

        return back();
    }
}
