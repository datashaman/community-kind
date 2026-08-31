<?php

namespace App\Http\Requests\Organisations;

use App\Enums\EligibilityStatus;
use App\Enums\IntakeStatus;
use App\Enums\IntakeUrgency;
use App\Models\IntakeRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionIntakeRequestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('risk_flags')) {
            $this->merge([
                'risk_flags' => array_values(array_filter(
                    $this->array('risk_flags'),
                    fn (mixed $value): bool => is_string($value) && $value !== '',
                )),
            ]);
        }
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
        $identifier = $this->route('intake');
        $intake = is_string($identifier) ? IntakeRequest::query()->find($identifier) : null;
        $eligibilityKeys = $intake instanceof IntakeRequest
            ? $this->configuredFieldKeys($intake, 'eligibility_fields')
            : [];
        $riskKeys = $intake instanceof IntakeRequest
            ? $this->configuredFieldKeys($intake, 'risk_flags')
            : [];

        return [
            'status' => ['required', Rule::enum(IntakeStatus::class)],
            'expected_version' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', Rule::in(['client_request', 'eligibility', 'capacity', 'external_referral', 'duplicate', 'other'])],
            'urgency' => ['nullable', Rule::enum(IntakeUrgency::class)],
            'eligibility_status' => ['nullable', Rule::enum(EligibilityStatus::class)],
            'eligibility_context' => [$eligibilityKeys === [] ? 'array' : 'array:'.implode(',', $eligibilityKeys)],
            'eligibility_context.*' => ['boolean'],
            'risk_flags' => ['nullable', 'array', 'max:20'],
            'risk_flags.*' => ['string', 'distinct', Rule::in($riskKeys)],
            'worker_membership_id' => ['nullable', 'integer'],
        ];
    }

    /** @return list<string> */
    private function configuredFieldKeys(IntakeRequest $intake, string $configurationKey): array
    {
        $definitions = $intake->program->configuration[$configurationKey] ?? null;

        if (! is_array($definitions)) {
            return [];
        }

        $keys = [];

        foreach ($definitions as $definition) {
            if (is_array($definition) && isset($definition['key']) && is_string($definition['key'])) {
                $keys[] = $definition['key'];
            }
        }

        return $keys;
    }
}
