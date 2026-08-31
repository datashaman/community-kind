<?php

namespace App\Actions\Volunteering;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\FindPartiesByContact;
use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Actions\Parties\StorePartyContact;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyContactType;
use App\Enums\PartyKind;
use App\Enums\PartyTimelineEventType;
use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerOnboardingStatus;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\SupporterRegistration;
use App\Models\VolunteerApplication;
use App\Models\VolunteerOpportunity;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RegisterVolunteerApplication
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly FindPartiesByContact $findParties,
        private readonly StorePartyContact $storeContact,
        private readonly RecordPartyConsent $recordConsent,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    /** @param array{name: string, email: string, interests: list<string>, availability: list<string>, consent_email: bool} $attributes */
    public function handle(Organisation $organisation, VolunteerOpportunity $opportunity, array $attributes): VolunteerApplication
    {
        $this->context->ensureOwns($organisation->id);

        return DB::transaction(function () use ($attributes, $opportunity, $organisation): VolunteerApplication {
            $opportunity = VolunteerOpportunity::query()->lockForUpdate()->findOrFail($opportunity->id);
            if (! $opportunity->acceptsRegistrations()) {
                throw new LogicException('This volunteer opportunity is not accepting registrations.');
            }

            $matches = $this->findParties->handle(PartyContactType::Email, $attributes['email'])
                ->filter(fn (Party $party): bool => $party->kind === PartyKind::Person);
            $party = $matches->count() === 1 ? $matches->first() : null;

            if (! $party instanceof Party) {
                $party = Party::query()->create(['kind' => PartyKind::Person, 'display_name' => $attributes['name']]);
                $this->storeContact->handle($party, PartyContactType::Email, $attributes['email']);
            }

            if (VolunteerApplication::query()->where('volunteer_opportunity_id', $opportunity->id)->where('party_id', $party->id)->exists()) {
                throw new LogicException('This person is already registered for the opportunity.');
            }

            PartyRole::query()->firstOrCreate([
                'organisation_id' => $organisation->id,
                'party_id' => $party->id,
                'role' => PartyBusinessRole::Volunteer,
            ]);
            $registration = SupporterRegistration::query()->create([
                'organisation_id' => $organisation->id,
                'party_id' => $party->id,
                'kind' => SupporterRegistrationKind::Volunteer,
                'title' => $opportunity->title,
                'status' => SupporterRegistrationStatus::Pending,
                'version' => 1,
                'starts_at' => $opportunity->starts_at,
            ]);
            $application = VolunteerApplication::query()->create([
                'organisation_id' => $organisation->id,
                'volunteer_opportunity_id' => $opportunity->id,
                'party_id' => $party->id,
                'supporter_registration_id' => $registration->id,
                'status' => VolunteerApplicationStatus::Submitted,
                'onboarding_status' => VolunteerOnboardingStatus::NotStarted,
                'interests' => $attributes['interests'],
                'availability' => $attributes['availability'],
                'follow_up_status' => $attributes['consent_email'] ? 'eligible' : 'suppressed',
                'version' => 1,
                'submitted_at' => now(),
            ]);
            $this->recordConsent->handle($party, [
                'purpose' => ConsentPurpose::SupporterUpdates,
                'channel' => ConsentChannel::Email,
                'decision' => $attributes['consent_email'] ? ConsentDecision::Granted : ConsentDecision::Suppressed,
                'wording_version' => 'volunteer-registration-v1',
                'wording' => 'I choose whether to receive volunteer updates by email.',
                'source' => 'public_volunteer_registration',
                'occurred_at' => now()->toAtomString(),
            ], null);
            $this->recordTimeline->handle($party, PartyTimelineEventType::VolunteerApplicationTransitioned, 'Volunteer application submitted.', subjectType: 'volunteer_application', subjectId: $application->id, metadata: ['status' => VolunteerApplicationStatus::Submitted->value]);
            $this->recordAudit->handle($organisation, TenantAuditEventType::VolunteerApplicationSubmitted, 'volunteer_application', $application->id, ['application_id' => $application->id, 'opportunity_id' => $opportunity->id, 'party_uuid' => $party->uuid]);

            return $application;
        });
    }
}
