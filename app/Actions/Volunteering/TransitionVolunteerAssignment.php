<?php

namespace App\Actions\Volunteering;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerAssignmentStatus;
use App\Models\User;
use App\Models\VolunteerAssignment;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TransitionVolunteerAssignment
{
    public function __construct(private readonly OrganisationContext $context, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    public function handle(VolunteerAssignment $assignment, VolunteerAssignmentStatus $to, User $actor): VolunteerAssignment
    {
        $this->context->ensureOwns($assignment->organisation_id);

        return DB::transaction(function () use ($actor, $assignment, $to): VolunteerAssignment {
            $locked = VolunteerAssignment::query()->with('party')->lockForUpdate()->findOrFail($assignment->id);
            $from = $locked->status;
            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition volunteer assignment from {$from->value} to {$to->value}.");
            }
            $locked->update(['status' => $to, 'version' => $locked->version + 1, 'cancelled_at' => $to === VolunteerAssignmentStatus::Cancelled ? now() : null, 'attended_at' => $to === VolunteerAssignmentStatus::Attended ? now() : null, 'transitioned_by_user_id' => $actor->id]);
            $this->recordTimeline->handle($locked->party, PartyTimelineEventType::VolunteerAssignmentTransitioned, "Volunteer shift changed from {$from->value} to {$to->value}.", $actor, 'volunteer_assignment', $locked->id, ['status' => $to->value]);
            $this->recordAudit->handle($locked->party->organisation, TenantAuditEventType::VolunteerAssignmentTransitioned, 'volunteer_assignment', $locked->id, ['assignment_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value], $actor);

            return $locked->refresh();
        });
    }
}
