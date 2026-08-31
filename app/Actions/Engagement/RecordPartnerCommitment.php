<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Enums\PartnerCommitmentStatus;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\PartnerCommitment;
use App\Models\PartnerProfile;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class RecordPartnerCommitment
{
    public function __construct(private readonly OrganisationContext $context, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    /** @param array{title: string, details: string, status: PartnerCommitmentStatus, due_on: string|null} $attributes */
    public function handle(PartnerProfile $profile, array $attributes, User $actor): PartnerCommitment
    {
        $this->context->ensureOwns($profile->organisation_id);

        return DB::transaction(function () use ($actor, $attributes, $profile): PartnerCommitment {
            $profile = PartnerProfile::query()->with('party')->lockForUpdate()->findOrFail($profile->id);
            $commitment = PartnerCommitment::query()->create(['organisation_id' => $profile->organisation_id, 'partner_profile_id' => $profile->id, ...$attributes, 'completed_at' => $attributes['status'] === PartnerCommitmentStatus::Completed ? now() : null, 'recorded_by_user_id' => $actor->id]);
            $this->recordTimeline->handle($profile->party, PartyTimelineEventType::PartnerEngagementRecorded, "Partner commitment recorded: {$commitment->title}.", $actor, 'partner_commitment', $commitment->id, ['status' => $commitment->status->value]);
            $this->recordAudit->handle($profile->party->organisation, TenantAuditEventType::PartnerCommitmentRecorded, 'partner_commitment', $commitment->id, ['commitment_id' => $commitment->id, 'partner_profile_id' => $profile->id, 'status' => $commitment->status->value], $actor);

            return $commitment;
        });
    }
}
