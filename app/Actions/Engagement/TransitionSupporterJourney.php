<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\SupporterJourneyStatus;
use App\Enums\TenantAuditEventType;
use App\Models\SupporterJourney;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

final class TransitionSupporterJourney
{
    public function __construct(private readonly OrganisationContext $context, private readonly RecordTenantAuditEvent $recordAudit) {}

    public function handle(SupporterJourney $journey, SupporterJourneyStatus $to, ?string $scheduledFor, User $actor): SupporterJourney
    {
        $this->context->ensureOwns($journey->organisation_id);

        return DB::transaction(function () use ($actor, $journey, $scheduledFor, $to): SupporterJourney {
            $locked = SupporterJourney::query()->lockForUpdate()->findOrFail($journey->id);
            $from = $locked->status;
            if (! in_array($to, $from->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition supporter journey from {$from->value} to {$to->value}.");
            }
            if ($to === SupporterJourneyStatus::Scheduled && (blank($scheduledFor) || CarbonImmutable::parse($scheduledFor)->isPast())) {
                throw new LogicException('A scheduled journey requires a future dispatch time.');
            }
            $locked->update([
                'status' => $to,
                'scheduled_for' => $to === SupporterJourneyStatus::Scheduled ? $scheduledFor : null,
                'paused_at' => $to === SupporterJourneyStatus::Paused ? now() : null,
                'version' => $locked->version + 1,
            ]);
            $this->recordAudit->handle($locked->organisation, TenantAuditEventType::SupporterJourneyTransitioned, 'supporter_journey', $locked->id, ['journey_id' => $locked->id, 'from_status' => $from->value, 'to_status' => $to->value, 'scheduled_for' => $locked->scheduled_for?->toAtomString()], $actor);

            return $locked->refresh();
        });
    }
}
