<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseNoteStatus;
use App\Enums\CaseWorkflowSubject;
use App\Models\CaseNote;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class SaveCaseNote
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly ClassifiedDataEncrypter $encrypter, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(ServiceCase $case, string $content, User $actor, ?CaseNote $corrects = null): CaseNote
    {
        $this->context->ensureOwns($case->organisation_id);

        if ($corrects !== null) {
            $this->context->ensureOwns($corrects->organisation_id);
            if ($corrects->service_case_id !== $case->id || $corrects->status !== CaseNoteStatus::Finalized) {
                throw new LogicException('A correction must reference a finalized note from the same Case.');
            }
        }

        return DB::transaction(function () use ($case, $content, $actor, $corrects): CaseNote {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot accept new work.');
            }
            $note = new CaseNote;
            $note->forceFill(['id' => $note->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'status' => CaseNoteStatus::Draft, 'corrects_note_id' => $corrects?->id, 'authored_by_user_id' => $actor->id]);
            $note->encrypted_content = new ClassifiedValue($content);
            $note->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Note, $note->id, null, $note->status->value, 1, now(), $actor, $corrects === null ? null : 'correction');

            return $note;
        });
    }
}
