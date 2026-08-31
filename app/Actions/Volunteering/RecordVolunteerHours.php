<?php

namespace App\Actions\Volunteering;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerAssignmentStatus;
use App\Models\User;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerHourEntry;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

final class RecordVolunteerHours
{
    public function __construct(private readonly OrganisationContext $context, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    public function handle(VolunteerAssignment $assignment, int $minutes, User $actor): VolunteerHourEntry
    {
        $this->context->ensureOwns($assignment->organisation_id);

        return DB::transaction(function () use ($actor, $assignment, $minutes): VolunteerHourEntry {
            $locked = VolunteerAssignment::query()->with(['party', 'shift'])->lockForUpdate()->findOrFail($assignment->id);
            if ($locked->status !== VolunteerAssignmentStatus::Attended || $minutes < 1 || $minutes > $locked->shift->starts_at->diffInMinutes($locked->shift->ends_at)) {
                throw new LogicException('Volunteer hours require attendance and cannot exceed the shift duration.');
            }
            $existing = VolunteerHourEntry::query()->where('volunteer_assignment_id', $locked->id)->first();
            if ($existing !== null) {
                if ($existing->minutes !== $minutes) {
                    throw new LogicException('Volunteer hours have already been recorded for this shift.');
                }

                return $existing;
            }
            $hours = VolunteerHourEntry::query()->create(['organisation_id' => $locked->organisation_id, 'volunteer_assignment_id' => $locked->id, 'party_id' => $locked->party_id, 'minutes' => $minutes, 'occurred_at' => $locked->attended_at ?? now(), 'recorded_by_user_id' => $actor->id]);
            $this->recordTimeline->handle($locked->party, PartyTimelineEventType::VolunteerHoursRecorded, 'Volunteer contribution recorded.', $actor, 'volunteer_hour_entry', $hours->id, ['minutes' => $minutes]);
            $this->recordAudit->handle($locked->party->organisation, TenantAuditEventType::VolunteerHoursRecorded, 'volunteer_hour_entry', $hours->id, ['hours_id' => $hours->id, 'assignment_id' => $locked->id, 'minutes' => $minutes], $actor);

            return $hours;
        });
    }
}
