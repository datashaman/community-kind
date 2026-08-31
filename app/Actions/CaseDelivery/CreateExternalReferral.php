<?php

namespace App\Actions\CaseDelivery;

use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseWorkflowSubject;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\ExternalReferralStatus;
use App\Models\ExternalReferral;
use App\Models\Party;
use App\Models\PartyConsent;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use LogicException;

class CreateExternalReferral
{
    public function __construct(private readonly OrganisationContext $context, private readonly EnsureCanManageCase $access, private readonly ClassifiedDataEncrypter $encrypter, private readonly RecordCaseWorkflowTransition $recordTransition) {}

    public function handle(ServiceCase $case, string $destination, string $purpose, string $minimumNecessary, string $sharingAuthority, User $actor): ExternalReferral
    {
        $this->context->ensureOwns($case->organisation_id);

        return DB::transaction(function () use ($case, $destination, $purpose, $minimumNecessary, $sharingAuthority, $actor): ExternalReferral {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->access->handle($case, $actor);
            if ($case->status->isTerminal()) {
                throw new LogicException('A terminal Case cannot accept new work.');
            }
            Party::query()->lockForUpdate()->findOrFail($case->party_id);
            $consent = PartyConsent::query()->where('party_id', $case->party_id)->where('purpose', ConsentPurpose::Service)->latest('occurred_at')->latest('id')->first();
            if ($sharingAuthority !== 'service_consent' || $consent?->decision !== ConsentDecision::Granted) {
                throw new LogicException('A current service-consent sharing authority is required.');
            }
            $referral = new ExternalReferral;
            $referral->forceFill(['id' => $referral->newUniqueId(), 'organisation_id' => $case->organisation_id, 'service_case_id' => $case->id, 'type' => 'content', 'data_key_version' => $this->encrypter->currentVersion(), 'status' => ExternalReferralStatus::Draft, 'sharing_authority' => $sharingAuthority, 'created_by_user_id' => $actor->id]);
            $referral->encrypted_content = new ClassifiedValue(json_encode(['destination' => $destination, 'purpose' => $purpose, 'minimum_necessary' => $minimumNecessary], JSON_THROW_ON_ERROR));
            $referral->save();
            $this->recordTransition->handle($case, CaseWorkflowSubject::Referral, $referral->id, null, $referral->status->value, 1, now(), $actor);

            return $referral;
        });
    }
}
