<?php

namespace App\Actions\Portal;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Engagement\TransitionEventRegistration;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Actions\Volunteering\TransitionVolunteerApplication;
use App\Enums\EventRegistrationStatus;
use App\Enums\PartyTimelineEventType;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerApplicationStatus;
use App\Models\PortalAccessGrant;
use App\Models\SupporterRegistration;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class CancelSupporterRegistration
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
        private readonly TransitionVolunteerApplication $transitionVolunteerApplication,
        private readonly TransitionEventRegistration $transitionEventRegistration,
    ) {}

    public function handle(PortalAccessGrant $grant, SupporterRegistration $registration): SupporterRegistration
    {
        $this->context->ensureOwns($grant->organisation_id);
        abort_unless($grant->hasActiveAccess(), 410);

        return DB::transaction(function () use ($grant, $registration): SupporterRegistration {
            $locked = SupporterRegistration::query()
                ->whereKey($registration->id)
                ->where('organisation_id', $grant->organisation_id)
                ->where('party_id', $grant->person_party_id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($locked->status->canCancel(), 422, 'This registration can no longer be cancelled.');
            $eventRegistration = $locked->eventRegistration()->first();
            abort_if(
                $eventRegistration !== null && ! in_array(EventRegistrationStatus::Cancelled, $eventRegistration->status->allowedTransitions(), true),
                422,
                'This event registration can no longer be cancelled.',
            );

            $locked->update([
                'status' => SupporterRegistrationStatus::Cancelled,
                'version' => $locked->version + 1,
                'cancelled_at' => now(),
            ]);
            $this->recordTimeline->handle(
                $grant->personParty,
                PartyTimelineEventType::SupporterRegistrationTransitioned,
                "Supporter cancelled {$locked->title}.",
                $grant->user,
                'supporter_registration',
                $locked->id,
                ['status' => SupporterRegistrationStatus::Cancelled->value],
            );
            $this->recordAudit->handle(
                $grant->organisation,
                TenantAuditEventType::SupporterRegistrationCancelled,
                'supporter_registration',
                $locked->id,
                [
                    'registration_id' => $locked->id,
                    'party_uuid' => $grant->personParty->uuid,
                    'kind' => $locked->kind->value,
                ],
                $grant->user,
            );
            $application = $locked->volunteerApplication()->first();
            if ($application !== null && in_array(VolunteerApplicationStatus::Withdrawn, $application->status->allowedTransitions(), true)) {
                $this->transitionVolunteerApplication->handle($application, VolunteerApplicationStatus::Withdrawn, $grant->user);
            }
            if ($eventRegistration !== null && in_array(EventRegistrationStatus::Cancelled, $eventRegistration->status->allowedTransitions(), true)) {
                $this->transitionEventRegistration->handle($eventRegistration, EventRegistrationStatus::Cancelled, $grant->user);
            }

            return $locked->refresh();
        });
    }
}
