<?php

namespace App\Actions\Volunteering;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\PartyTimelineEventType;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerAssignmentStatus;
use App\Enums\VolunteerCredentialStatus;
use App\Enums\VolunteerOnboardingStatus;
use App\Enums\VolunteerShiftStatus;
use App\Models\PartyConsent;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerShift;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TransitionVolunteerApplication
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly TransitionVolunteerAssignment $transitionAssignment,
        private readonly RecordPartyTimelineEvent $recordTimeline,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    public function handle(VolunteerApplication $application, VolunteerApplicationStatus $to, User $actor): VolunteerApplication
    {
        $this->context->ensureOwns($application->organisation_id);

        return DB::transaction(function () use ($actor, $application, $to): VolunteerApplication {
            $locked = VolunteerApplication::query()->with(['credentials', 'opportunity', 'party', 'registration'])->lockForUpdate()->findOrFail($application->id);
            $from = $locked->status;
            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition volunteer application from {$from->value} to {$to->value}.");
            }
            if ($to === VolunteerApplicationStatus::Approved && $locked->credentials->contains(fn ($credential): bool => $credential->status !== VolunteerCredentialStatus::Verified || ($credential->expires_at !== null && $credential->expires_at->isPast()))) {
                throw new LogicException('All recorded volunteer credentials must be current before approval.');
            }

            $onboarding = match ($to) {
                VolunteerApplicationStatus::Onboarding => VolunteerOnboardingStatus::InProgress,
                VolunteerApplicationStatus::Approved => VolunteerOnboardingStatus::Complete,
                default => $locked->onboarding_status,
            };
            $latestConsent = PartyConsent::query()->where('party_id', $locked->party_id)->where('purpose', ConsentPurpose::SupporterUpdates)->where('channel', ConsentChannel::Email)->latest('occurred_at')->latest('id')->first();
            $locked->update([
                'status' => $to,
                'onboarding_status' => $onboarding,
                'follow_up_status' => $latestConsent?->decision === ConsentDecision::Granted ? 'eligible' : 'suppressed',
                'version' => $locked->version + 1,
                'reviewed_at' => now(),
                'reviewed_by_user_id' => $actor->id,
            ]);
            if (in_array($to, [VolunteerApplicationStatus::Rejected, VolunteerApplicationStatus::Withdrawn], true)) {
                if ($locked->registration->status !== SupporterRegistrationStatus::Cancelled) {
                    $locked->registration->update(['status' => SupporterRegistrationStatus::Cancelled, 'version' => $locked->registration->version + 1, 'cancelled_at' => now()]);
                }
                VolunteerAssignment::query()
                    ->where('volunteer_application_id', $locked->id)
                    ->where('status', VolunteerAssignmentStatus::Confirmed)
                    ->lockForUpdate()
                    ->get()
                    ->each(fn (VolunteerAssignment $assignment) => $this->transitionAssignment->handle($assignment, VolunteerAssignmentStatus::Cancelled, $actor));
            } elseif ($to === VolunteerApplicationStatus::Approved) {
                $locked->registration->update(['status' => SupporterRegistrationStatus::Confirmed, 'version' => $locked->registration->version + 1]);
                $shift = VolunteerShift::query()->where('volunteer_opportunity_id', $locked->volunteer_opportunity_id)->where('status', VolunteerShiftStatus::Open)->where('starts_at', '>', now())->orderBy('starts_at')->lockForUpdate()->get()->first(fn (VolunteerShift $shift): bool => $shift->assignments()->where('status', VolunteerAssignmentStatus::Confirmed)->count() < $shift->capacity);
                if ($shift !== null) {
                    $assignment = VolunteerAssignment::query()->firstOrCreate(
                        ['organisation_id' => $locked->organisation_id, 'volunteer_shift_id' => $shift->id, 'party_id' => $locked->party_id],
                        ['volunteer_application_id' => $locked->id, 'status' => VolunteerAssignmentStatus::Confirmed, 'version' => 1, 'confirmed_at' => now(), 'transitioned_by_user_id' => $actor->id],
                    );
                    if ($assignment->wasRecentlyCreated) {
                        $this->recordTimeline->handle($locked->party, PartyTimelineEventType::VolunteerAssignmentTransitioned, 'Volunteer shift confirmed.', $actor, 'volunteer_assignment', $assignment->id, ['status' => VolunteerAssignmentStatus::Confirmed->value]);
                        $this->recordAudit->handle($locked->party->organisation, TenantAuditEventType::VolunteerAssignmentTransitioned, 'volunteer_assignment', $assignment->id, ['assignment_id' => $assignment->id, 'from_status' => 'unassigned', 'to_status' => VolunteerAssignmentStatus::Confirmed->value], $actor);
                    }
                }
            }
            $this->recordTimeline->handle($locked->party, PartyTimelineEventType::VolunteerApplicationTransitioned, "Volunteer application changed from {$from->value} to {$to->value}.", $actor, 'volunteer_application', $locked->id, ['status' => $to->value]);
            $this->recordAudit->handle($locked->party->organisation, TenantAuditEventType::VolunteerApplicationTransitioned, 'volunteer_application', $locked->id, ['application_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value], $actor);

            return $locked->refresh();
        });
    }
}
