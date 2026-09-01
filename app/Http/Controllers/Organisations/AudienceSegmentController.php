<?php

namespace App\Http\Controllers\Organisations;

use App\Actions\Engagement\CreateAudienceSegment;
use App\Actions\Engagement\EvaluateAudienceSegment;
use App\Enums\AudienceActivityType;
use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\PartyBusinessRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organisations\StoreAudienceSegmentRequest;
use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\Organisation;
use App\Models\PartyAddress;
use App\Models\PartyInterest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AudienceSegmentController extends Controller
{
    public function index(Organisation $currentOrganisation, EvaluateAudienceSegment $evaluate): Response
    {
        Gate::authorize('viewAny', [AudienceSegment::class, $currentOrganisation]);

        return Inertia::render('audience-segments/index', [
            'segments' => AudienceSegment::query()->orderBy('name')->get()->map(fn (AudienceSegment $segment): array => [
                'id' => $segment->id,
                'name' => $segment->name,
                'criteria' => $segment->criteria,
                'eligibleCount' => $evaluate->handle($segment)->count(),
            ]),
            'options' => [
                'purpose' => ConsentPurpose::SupporterUpdates->value,
                'channels' => collect([ConsentChannel::Email, ConsentChannel::Sms, ConsentChannel::Telephone])->map(fn (ConsentChannel $channel): array => ['value' => $channel->value, 'label' => str($channel->value)->replace('_', ' ')->title()->toString()]),
                'roles' => collect([PartyBusinessRole::Donor, PartyBusinessRole::Volunteer, PartyBusinessRole::PartnerContact, PartyBusinessRole::EventAttendee])->map(fn (PartyBusinessRole $role): array => ['value' => $role->value, 'label' => $role->label()]),
                'serviceAreas' => PartyAddress::query()->whereNotNull('service_area')->distinct()->orderBy('service_area')->pluck('service_area'),
                'interests' => PartyInterest::query()->select(['slug', 'label'])->distinct()->orderBy('label')->get(),
                'campaignSources' => Donation::query()->distinct()->orderBy('source_code')->pluck('source_code'),
                'activityTypes' => collect(AudienceActivityType::cases())->map(fn (AudienceActivityType $type): array => ['value' => $type->value, 'label' => $type->label()]),
            ],
        ]);
    }

    public function store(StoreAudienceSegmentRequest $request, Organisation $currentOrganisation, CreateAudienceSegment $create): RedirectResponse
    {
        Gate::authorize('create', [AudienceSegment::class, $currentOrganisation]);
        $validated = $request->validated();
        $segment = $create->handle(
            $currentOrganisation,
            $validated['name'],
            [
                'purpose' => $validated['purpose'],
                'channel' => $validated['channel'],
                'role' => $validated['role'],
                'service_area' => $validated['service_area'] ?? null,
                'interest' => $validated['interest'] ?? null,
                'donation_activity' => (bool) $validated['donation_activity'],
                'campaign_source' => $validated['campaign_source'] ?? null,
                'activity_type' => $validated['activity_type'],
                'recency_days' => isset($validated['recency_days']) ? (int) $validated['recency_days'] : null,
                'minimum_frequency' => (int) $validated['minimum_frequency'],
                'minimum_value' => isset($validated['minimum_value']) ? (int) $validated['minimum_value'] : null,
            ],
            $request->user(),
        );

        return to_route('audience-segments.show', [$currentOrganisation, $segment]);
    }

    public function show(Organisation $currentOrganisation, string $audienceSegment, EvaluateAudienceSegment $evaluate): Response
    {
        $segment = AudienceSegment::query()->findOrFail($audienceSegment);
        Gate::authorize('view', $segment);
        $audience = $evaluate->handle($segment);

        return Inertia::render('audience-segments/show', [
            'segment' => ['id' => $segment->id, 'name' => $segment->name, 'criteria' => $segment->criteria],
            'audience' => $audience,
            'eligibleCount' => $audience->count(),
        ]);
    }
}
