<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntakeRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organisation = $this->route('current_organisation');
        $organisationId = $organisation instanceof Organisation ? $organisation->id : 0;
        $program = Program::query()->whereKey($this->integer('program_id'))->first();
        $fieldDefinitions = $program === null ? [] : $this->configuredFields($program, 'intake_fields');
        $fieldKeys = $this->configuredFieldKeys($fieldDefinitions);
        $riskKeys = $program === null ? [] : $this->configuredFieldKeys($this->configuredFields($program, 'risk_flags'));
        $rules = [
            'program_id' => ['required', 'integer', Rule::exists('programs', 'id')->where('organisation_id', $organisationId)],
            'party_uuid' => ['required', 'uuid', Rule::exists('parties', 'uuid')->where('organisation_id', $organisationId)],
            'source' => ['required', Rule::in(['staff_referral', 'self_referral', 'partner_referral', 'phone', 'walk_in', 'online'])],
            'narrative' => ['required', 'string', 'max:10000'],
            'presenting_needs' => ['required', 'string', 'max:10000'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'intake_fields' => [$fieldKeys === [] ? 'array' : 'array:'.implode(',', $fieldKeys)],
            'risk_flags' => ['present', 'array', 'max:20'],
            'risk_flags.*' => ['string', 'distinct', Rule::in($riskKeys)],
            'consent_granted' => ['required', 'boolean'],
            'consent_source' => ['required_if:consent_granted,true', 'nullable', Rule::in(['verbal', 'written', 'online', 'referral'])],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];

        foreach ($fieldDefinitions as $definition) {
            $key = $definition['key'] ?? null;

            if (! is_string($key)) {
                continue;
            }

            $fieldRules = [($definition['required'] ?? false) === true ? 'required' : 'nullable'];
            $fieldRules[] = match ($definition['type'] ?? 'text') {
                'boolean' => 'boolean',
                'date' => 'date',
                default => 'string',
            };
            $fieldRules[] = 'max:'.(($definition['type'] ?? null) === 'textarea' ? '10000' : '255');
            $rules['intake_fields.'.$key] = $fieldRules;
        }

        return $rules;
    }

    /** @return list<array<string, mixed>> */
    private function configuredFields(Program $program, string $key): array
    {
        $value = $program->configuration[$key] ?? null;

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_array(...)));
    }

    /** @param list<array<string, mixed>> $definitions
     * @return list<string>
     */
    private function configuredFieldKeys(array $definitions): array
    {
        $keys = [];

        foreach ($definitions as $definition) {
            if (isset($definition['key']) && is_string($definition['key'])) {
                $keys[] = $definition['key'];
            }
        }

        return $keys;
    }
}
