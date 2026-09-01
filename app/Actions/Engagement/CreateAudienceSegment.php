<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\TenantAuditEventType;
use App\Models\AudienceSegment;
use App\Models\Organisation;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

class CreateAudienceSegment
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly RecordTenantAuditEvent $recordAudit,
    ) {}

    /** @param array{purpose: string, channel: string, role: string, service_area: string|null, interest: string|null, donation_activity: bool, campaign_source: string|null, activity_type: string, recency_days: int|null, minimum_frequency: int, minimum_value: int|null} $criteria */
    public function handle(Organisation $organisation, string $name, array $criteria, User $actor): AudienceSegment
    {
        $this->context->ensureOwns($organisation->id);

        return DB::transaction(function () use ($actor, $criteria, $name, $organisation): AudienceSegment {
            $segment = AudienceSegment::query()->create([
                'organisation_id' => $organisation->id,
                'name' => $name,
                'criteria' => $criteria,
                'created_by_user_id' => $actor->id,
            ]);
            $this->recordAudit->handle($organisation, TenantAuditEventType::AudienceSegmentCreated, 'audience_segment', $segment->id, [
                'segment_id' => $segment->id,
                'name' => $segment->name,
            ], $actor);

            return $segment;
        });
    }
}
