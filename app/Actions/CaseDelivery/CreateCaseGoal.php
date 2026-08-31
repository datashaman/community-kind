<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseGoalStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseGoal;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateCaseGoal
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly ClassifiedDataEncrypter $encrypter, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(ServiceCase $case, string $title, string $description, ?CarbonInterface $targetAt, User $actor): CaseGoal
    {
        $this->context->ensureOwns($case->organisation_id);

        return DB::transaction(function () use ($case, $title, $description, $targetAt, $actor): CaseGoal {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot accept new work.');
            }
            $goal = new CaseGoal;
            $goal->forceFill(['id' => $goal->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'status' => CaseGoalStatus::Draft, 'target_at' => $targetAt, 'created_by_user_id' => $actor->id]);
            $goal->encrypted_content = new ClassifiedValue(json_encode(compact('title', 'description'), JSON_THROW_ON_ERROR));
            $goal->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Goal, $goal->id, null, $goal->status->value, 1, now(), $actor);

            return $goal;
        });
    }
}
