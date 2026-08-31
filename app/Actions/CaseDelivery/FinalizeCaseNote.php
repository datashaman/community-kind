<?php

namespace App\Actions\CaseDelivery;

use App\Enums\CaseNoteStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseNote;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class FinalizeCaseNote
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(CaseNote $note, int $expectedVersion, User $actor): CaseNote
    {
        $this->context->ensureOwns($note->organisation_id);

        return DB::transaction(function () use ($note, $expectedVersion, $actor): CaseNote {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($note->service_case_id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot transition its work.');
            }
            $locked = CaseNote::query()->lockForUpdate()->findOrFail($note->id);
            if ($locked->status === CaseNoteStatus::Finalized) {
                return $locked;
            }
            if ($locked->version !== $expectedVersion) {
                throw new LogicException('The note changed while it was being reviewed.');
            }

            $locked->forceFill(['status' => CaseNoteStatus::Finalized, 'version' => $locked->version + 1, 'finalized_at' => now()])->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Note, $locked->id, CaseNoteStatus::Draft->value, CaseNoteStatus::Finalized->value, $locked->version, $locked->finalized_at, $actor);

            return $locked->refresh();
        });
    }
}
