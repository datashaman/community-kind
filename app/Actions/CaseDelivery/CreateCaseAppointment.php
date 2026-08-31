<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseAppointmentStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseAppointment;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateCaseAppointment
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly ClassifiedDataEncrypter $encrypter, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(ServiceCase $case, string $summary, string $location, CarbonInterface $scheduledAt, User $actor): CaseAppointment
    {
        $this->context->ensureOwns($case->organisation_id);

        return DB::transaction(function () use ($case, $summary, $location, $scheduledAt, $actor): CaseAppointment {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot accept new work.');
            }
            $appointment = new CaseAppointment;
            $appointment->forceFill(['id' => $appointment->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'status' => CaseAppointmentStatus::Scheduled, 'scheduled_at' => $scheduledAt, 'created_by_user_id' => $actor->id]);
            $appointment->encrypted_content = new ClassifiedValue(json_encode(compact('summary', 'location'), JSON_THROW_ON_ERROR));
            $appointment->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Appointment, $appointment->id, null, $appointment->status->value, 1, now(), $actor);

            return $appointment;
        });
    }
}
