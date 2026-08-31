<?php

namespace App\Actions\CaseDelivery;

use App\Enums\ExternalReferralStatus;
use App\Models\ExternalReferral;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class CarryForwardExternalReferral
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly CorrectCaseWorkflowRecord $correctRecord) {}

    public function handle(ExternalReferral $referral, int $expectedVersion, string $reason, CarbonInterface $effectiveAt, User $actor): ExternalReferral
    {
        $this->context->ensureOwns($referral->organisation_id);

        return DB::transaction(function () use ($referral, $expectedVersion, $reason, $effectiveAt, $actor): ExternalReferral {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($referral->service_case_id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot transition its work.');
            }
            $locked = ExternalReferral::query()->lockForUpdate()->findOrFail($referral->id);

            if (! in_array($locked->status, [ExternalReferralStatus::Sent, ExternalReferralStatus::Acknowledged], true)) {
                throw new LogicException('Only a pending referral can be carried forward.');
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The referral changed while it was being reviewed.');
            }
            if (blank($reason)) {
                throw new LogicException('A carry-forward reason code is required.');
            }

            $locked->forceFill(['version' => $locked->version + 1, 'carried_forward_at' => $effectiveAt, 'carry_forward_reason' => $reason])->save();
            $this->correctRecord->handle($case, $locked->id, 'referral', 'carry_forward', $reason, ['status' => $locked->status->value], $effectiveAt, $actor);

            return $locked->refresh();
        });
    }
}
