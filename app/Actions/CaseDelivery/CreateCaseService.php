<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseServiceStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseService;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateCaseService
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly ClassifiedDataEncrypter $encrypter, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(ServiceCase $case, string $serviceCode, string $summary, ?CarbonInterface $scheduledFor, User $actor): CaseService
    {
        $this->context->ensureOwns($case->organisation_id);

        return DB::transaction(function () use ($case, $serviceCode, $summary, $scheduledFor, $actor): CaseService {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot accept new work.');
            }
            $service = new CaseService;
            $service->forceFill(['id' => $service->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'service_code' => $serviceCode, 'status' => CaseServiceStatus::Planned, 'scheduled_for' => $scheduledFor, 'created_by_user_id' => $actor->id]);
            $service->encrypted_content = new ClassifiedValue(json_encode(compact('summary'), JSON_THROW_ON_ERROR));
            $service->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Service, $service->id, null, $service->status->value, 1, now(), $actor);

            return $service;
        });
    }
}
