<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Models\CaseInteraction;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class RecordCaseInteraction
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly ClassifiedDataEncrypter $encrypter) {}

    public function handle(ServiceCase $case, string $type, string $summary, CarbonInterface $occurredAt, User $actor): CaseInteraction
    {
        $this->context->ensureOwns($case->organisation_id);

        return DB::transaction(function () use ($case, $type, $summary, $occurredAt, $actor): CaseInteraction {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot accept new work.');
            }
            $interaction = new CaseInteraction;
            $interaction->forceFill(['id' => $interaction->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'interaction_type' => $type, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'occurred_at' => $occurredAt, 'recorded_at' => now(), 'recorded_by_user_id' => $actor->id]);
            $interaction->encrypted_content = new ClassifiedValue(json_encode(compact('summary'), JSON_THROW_ON_ERROR));
            $interaction->save();

            return $interaction;
        });
    }
}
