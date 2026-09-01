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
            ? $intake->program->eligibilityQuestions()
                ->where(fn ($query) => $query->whereNull('retired_at')->orWhereIn('key', array_keys($intake->eligibility_context)))
                ->pluck('key')->all()
            : [];
        $riskKeys = $intake instanceof IntakeRequest
            ? $intake->program->riskFlags()
                ->where(fn ($query) => $query->whereNull('retired_at')->orWhereIn('key', $intake->risk_flags))
                ->pluck('key')->all()
            : [];

        $rules = [
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

        if ($intake instanceof IntakeRequest) {
            foreach ($intake->program->eligibilityQuestions()->whereNull('retired_at')->where('is_required', true)->pluck('key') as $key) {
                $rules['eligibility_context.'.$key] = ['required', 'boolean'];
            }
        }

        return $rules;
    }
}
