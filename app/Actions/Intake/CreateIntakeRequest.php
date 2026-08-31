<?php

namespace App\Actions\Intake;

use App\Actions\Parties\FindPartiesByContact;
use App\Cryptography\ClassifiedDataEncrypter;
use App\Data\Values\ClassifiedValue;
use App\Enums\IntakeStatus;
use App\Enums\PartyContactType;
use App\Models\IntakeRequest;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyDuplicateReview;
use App\Models\Program;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateIntakeRequest
{
    public function __construct(
        private readonly OrganisationContext $organisationContext,
        private readonly ClassifiedDataEncrypter $encrypter,
        private readonly FindPartiesByContact $findPartiesByContact,
    ) {}

    /**
     * @param  array{source: string, narrative: string, presenting_needs: string, intake_fields: array<string, mixed>, eligibility_context: array<string, mixed>, risk_flags: list<string>, email: string|null, telephone: string|null, idempotency_key: string|null, consent_granted: bool, consent_source: string}  $attributes
     */
    public function handle(Organisation $organisation, Program $program, Party $party, array $attributes, User $actor): IntakeRequest
    {
        $this->organisationContext->ensureOwns($organisation->id);
        $this->organisationContext->ensureOwns($program->organisation_id);
        $this->organisationContext->ensureOwns($party->organisation_id);

        try {
            return DB::transaction(function () use ($organisation, $program, $party, $attributes, $actor): IntakeRequest {
                if ($attributes['idempotency_key'] !== null) {
                    $existing = IntakeRequest::query()->where('idempotency_key', $attributes['idempotency_key'])->first();

                    if ($existing !== null) {
                        return $existing;
                    }
                }

                $intake = new IntakeRequest;
                $intake->forceFill([
                    'id' => $intake->newUniqueId(),
                    'organisation_id' => $organisation->id,
                    'program_id' => $program->id,
                    'party_id' => $party->id,
                    'type' => 'content',
                    'data_key_version' => $this->encrypter->currentVersion(),
                    'eligibility_context' => $attributes['eligibility_context'],
                    'risk_flags' => $attributes['risk_flags'],
                    'status' => IntakeStatus::Draft,
                    'source' => $attributes['source'],
                    'idempotency_key' => $attributes['idempotency_key'],
                    'created_by_user_id' => $actor->id,
                ]);
                $intake->encrypted_content = new ClassifiedValue(json_encode([
                    'narrative' => $attributes['narrative'],
                    'presenting_needs' => $attributes['presenting_needs'],
                    'intake_fields' => $attributes['intake_fields'],
                    'service_consent' => [
                        'granted' => $attributes['consent_granted'],
                        'source' => $attributes['consent_source'],
                        'wording_version' => 'service-intake-v1',
                        'captured_at' => now()->toAtomString(),
                    ],
                ], JSON_THROW_ON_ERROR));
                $intake->save();
                $intake->transitions()->create([
                    'organisation_id' => $organisation->id,
                    'from_status' => null,
                    'to_status' => IntakeStatus::Draft,
                    'effective_at' => now(),
                    'recorded_at' => now(),
                    'version' => 1,
                    'actor_user_id' => $actor->id,
                ]);
                $this->recordDuplicateSuggestions($intake, $party, $attributes['email'], $attributes['telephone']);

                return $intake;
            });
        } catch (QueryException $exception) {
            if ($attributes['idempotency_key'] === null) {
                throw $exception;
            }

            $existing = IntakeRequest::query()->where('idempotency_key', $attributes['idempotency_key'])->first();

            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }
    }

    private function recordDuplicateSuggestions(IntakeRequest $intake, Party $submittedParty, ?string $email, ?string $telephone): void
    {
        /** @var array<int, array{candidate: Party, fields: list<string>}> $matches */
        $matches = [];

        foreach ([PartyContactType::Email->value => $email, PartyContactType::Telephone->value => $telephone] as $typeValue => $value) {
            if (! is_string($value) || $value === '') {
                continue;
            }

            $type = PartyContactType::from($typeValue);

            foreach ($this->findPartiesByContact->handle($type, $value) as $candidate) {
                if ($candidate->is($submittedParty)) {
                    continue;
                }

                $matches[$candidate->id] ??= ['candidate' => $candidate, 'fields' => []];
                $matches[$candidate->id]['fields'][] = $type->value;
            }
        }

        foreach ($matches as $match) {
            PartyDuplicateReview::query()->create([
                'organisation_id' => $intake->organisation_id,
                'intake_request_id' => $intake->id,
                'submitted_party_id' => $submittedParty->id,
                'candidate_party_id' => $match['candidate']->id,
                'matched_fields' => array_values(array_unique($match['fields'])),
            ]);
        }
    }
}
