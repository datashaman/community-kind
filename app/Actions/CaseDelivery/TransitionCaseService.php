<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseMetricCode;
use App\Enums\CaseServiceStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseService;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class TransitionCaseService
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly RecordCaseWorkflowTransition $recordTransition, private readonly RecordCaseMetric $recordMetric) {}

    public function handle(CaseService $service, CaseServiceStatus $to, int $expectedVersion, CarbonInterface $effectiveAt, User $actor, ?string $reason = null): CaseService
    {
        $this->context->ensureOwns($service->organisation_id);

        return DB::transaction(function () use ($service, $to, $expectedVersion, $effectiveAt, $actor, $reason): CaseService {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($service->service_case_id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot transition its work.');
            }
            $locked = CaseService::query()->lockForUpdate()->findOrFail($service->id);

            if ($locked->status === $to) {
                return $locked;
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The service changed while it was being reviewed.');
            }
            if (! in_array($to, $locked->status->allowedTransitions(), true)) {
                throw new LogicException("Cannot transition service from {$locked->status->value} to {$to->value}.");
            }
            if (in_array($to, [CaseServiceStatus::Cancelled, CaseServiceStatus::NotDelivered], true) && blank($reason)) {
                throw new LogicException('Cancelled or not-delivered services require a reason code.');
            }

            $from = $locked->status;
            $locked->forceFill(['status' => $to, 'version' => $locked->version + 1, 'delivered_at' => $to === CaseServiceStatus::Completed ? $effectiveAt : null, 'terminal_reason' => $to->isTerminal() && $to !== CaseServiceStatus::Completed ? $reason : null])->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Service, $locked->id, $from->value, $to->value, $locked->version, $effectiveAt, $actor, $reason);

            if ($to === CaseServiceStatus::Completed) {
                $this->recordMetric->handle($case, CaseMetricCode::ServiceDelivered, $effectiveAt, "service:{$locked->id}:{$locked->version}", ['service_code' => $locked->service_code]);
            }

            return $locked->refresh();
        });
    }
}
