<?php

namespace App\Http\Requests\Organisations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionCaseItemRequest extends FormRequest
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
        return [
            'status' => ['required', Rule::in(['active', 'achieved', 'not_achieved', 'cancelled', 'withdrawn', 'scheduled', 'completed', 'not_delivered', 'sent', 'acknowledged', 'connected', 'not_connected', 'carry_forward', 'no_show', 'finalized'])],
            'expected_version' => ['required', 'integer', 'min:1'],
            'effective_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', Rule::in(['achieved', 'not_achieved', 'withdrawn', 'no_longer_needed', 'client_cancelled', 'provider_cancelled', 'not_available', 'unable_to_connect', 'no_show', 'completed_elsewhere', 'follow_up_in_new_case', 'stable_tenancy'])],
            'completed_service_id' => ['nullable', 'uuid'],
        ];
    }
}
