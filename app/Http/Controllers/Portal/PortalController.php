<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\PartyContactType;
use App\Enums\RecurringMandateStatus;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\PartyConsent;
use App\Models\PartyContactPoint;
use App\Models\PortalAccessGrant;
use App\Models\RecurringMandate;
use App\Models\SupporterRegistration;
use App\Models\VolunteerApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $organisation = $request->attributes->get('public_organisation');
        $grant = $request->attributes->get('portal_access_grant');
        abort_unless($organisation instanceof Organisation && $grant instanceof PortalAccessGrant, 404);
        $party = $grant->personParty;

        $contacts = PartyContactPoint::query()
            ->where('party_id', $party->id)
            ->whereIn('type', [PartyContactType::Email, PartyContactType::Telephone])
            ->get()
            ->keyBy(fn (PartyContactPoint $contact): string => $contact->type->value);
        $preferences = collect([ConsentChannel::Email, ConsentChannel::Sms, ConsentChannel::Telephone])
            ->mapWithKeys(function (ConsentChannel $channel) use ($party): array {
                $latest = PartyConsent::query()
                    ->where('party_id', $party->id)
                    ->where('purpose', ConsentPurpose::SupporterUpdates)
                    ->where('channel', $channel)
                    ->latest('occurred_at')
                    ->latest('id')
                    ->first();

                return [$channel->value => $latest?->decision === ConsentDecision::Granted];
            });

        return Inertia::render('portal/show', [
            'organisation' => ['name' => $organisation->name, 'slug' => $organisation->slug],
            'profile' => [
                'displayName' => $party->display_name,
                'email' => $contacts->get(PartyContactType::Email->value)?->encrypted_value->reveal(),
                'telephone' => $contacts->get(PartyContactType::Telephone->value)?->encrypted_value->reveal(),
            ],
            'preferences' => $preferences,
            'recurringMandates' => RecurringMandate::query()
                ->where('party_id', $party->id)
                ->latest()
                ->get()
                ->map(fn (RecurringMandate $mandate): array => [
                    'id' => $mandate->id,
                    'amountMinor' => $mandate->amount_minor,
                    'currency' => $mandate->currency,
                    'interval' => $mandate->interval,
                    'status' => str($mandate->status->value)->headline()->toString(),
                    'canCancel' => $mandate->status !== RecurringMandateStatus::Cancelled,
                ]),
            'registrations' => SupporterRegistration::query()
                ->with(['volunteerApplication.credentials', 'volunteerApplication.assignments.shift', 'volunteerApplication.assignments.hours'])
                ->where('party_id', $party->id)
                ->orderByRaw('starts_at asc nulls last')
                ->get()
                ->map(function (SupporterRegistration $registration): array {
                    $application = $registration->volunteerApplication;

                    return [
                        'id' => $registration->id,
                        'kind' => $registration->kind->label(),
                        'title' => $registration->title,
                        'status' => $registration->status->label(),
                        'startsAt' => $registration->starts_at?->toAtomString(),
                        'canCancel' => $registration->status->canCancel(),
                        'volunteer' => $application instanceof VolunteerApplication ? [
                            'applicationStatus' => $application->status->value,
                            'onboardingStatus' => $application->onboarding_status->value,
                            'credentials' => $application->credentials->map(fn ($credential): array => ['type' => $credential->type, 'status' => $credential->effectiveStatus()->value, 'expiresAt' => $credential->expires_at?->toAtomString()])->values()->all(),
                            'assignments' => $application->assignments->map(fn ($assignment): array => ['title' => $assignment->shift->title, 'startsAt' => $assignment->shift->starts_at->toAtomString(), 'status' => $assignment->status->value, 'minutes' => $assignment->hours?->minutes])->values()->all(),
                        ] : null,
                    ];
                }),
        ]);
    }
}
