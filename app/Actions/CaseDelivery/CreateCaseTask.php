<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseTaskStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseTask;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateCaseTask
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly ClassifiedDataEncrypter $encrypter, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(ServiceCase $case, string $title, string $details, ?CarbonInterface $dueAt, User $actor): CaseTask
    {
        $this->context->ensureOwns($case->organisation_id);

        return DB::transaction(function () use ($case, $title, $details, $dueAt, $actor): CaseTask {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot accept new work.');
            }
            $task = new CaseTask;
            $task->forceFill(['id' => $task->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'status' => CaseTaskStatus::Open, 'due_at' => $dueAt, 'created_by_user_id' => $actor->id]);
            $task->encrypted_content = new ClassifiedValue(json_encode(compact('title', 'details'), JSON_THROW_ON_ERROR));
            $task->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Task, $task->id, null, $task->status->value, 1, now(), $actor);

            return $task;
        });
    }
}
