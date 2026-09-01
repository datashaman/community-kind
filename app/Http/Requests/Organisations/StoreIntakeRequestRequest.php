<?php

namespace App\Http\Requests\Organisations;

use App\Enums\IntakeUrgency;
use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntakeRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $configuration = $this->activeRules();
        $this->merge(['urgency' => $this->input('urgency', $configuration?->definition['default_urgency'] ?? IntakeUrgency::Routine->value)]);
    }

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
        $fieldDefinitions = $program?->intakeFields()->whereNull('retired_at')->get() ?? collect();
        $fieldKeys = $fieldDefinitions->pluck('key')->all();
        $riskKeys = $program?->riskFlags()->whereNull('retired_at')->pluck('key')->all() ?? [];
        $required = array_values(array_map('strval', $this->activeRules()?->definition['required_fields'] ?? []));
        $rules = [
            'program_id' => ['required', 'integer', Rule::exists('programs', 'id')->where('organisation_id', $organisationId)],
            'party_uuid' => ['required', 'uuid', Rule::exists('parties', 'uuid')->where('organisation_id', $organisationId)],
            'source' => ['required', Rule::in(['staff_referral', 'self_referral', 'partner_referral', 'phone', 'walk_in', 'online'])],
            'narrative' => ['required', 'string', 'max:10000'],
            'presenting_needs' => ['required', 'string', 'max:10000'],
            'urgency' => ['required', Rule::enum(IntakeUrgency::class)],
            'email' => [Rule::requiredIf(in_array('email', $required, true)), 'nullable', 'email:rfc', 'max:255'],
            'telephone' => [Rule::requiredIf(in_array('telephone', $required, true)), 'nullable', 'string', 'max:50'],
            'intake_fields' => [$fieldKeys === [] ? 'array' : 'array:'.implode(',', $fieldKeys)],
            'risk_flags' => ['present', 'array', 'max:20'],
            'risk_flags.*' => ['string', 'distinct', Rule::in($riskKeys)],
            'consent_granted' => ['required', 'boolean'],
            'consent_source' => ['required_if:consent_granted,true', 'nullable', Rule::in(['verbal', 'written', 'online', 'referral'])],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];

        foreach ($fieldDefinitions as $definition) {
            $fieldRules = [$definition->is_required ? 'required' : 'nullable'];
            $fieldRules[] = match ($definition->field_type->value) {
                'boolean' => 'boolean',
                'date' => 'date',
                default => 'string',
            };
            $fieldRules[] = 'max:'.($definition->field_type->value === 'textarea' ? '10000' : '255');
            $rules['intake_fields.'.$definition->key] = $fieldRules;
        }

        return $rules;
    }

    private function activeRules(): ?OrganisationConfiguration
    {
        return OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::IntakeRules)->where('configuration_key', 'default')->where('status', OrganisationConfigurationStatus::Active)->latest('version')->first();
    }
}
