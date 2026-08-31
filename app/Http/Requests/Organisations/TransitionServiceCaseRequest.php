<?php

namespace App\Http\Requests\Organisations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionServiceCaseRequest extends FormRequest
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
            'status' => ['required', Rule::in(['active', 'on_hold', 'closed', 'cancelled'])],
            'expected_version' => ['required', 'integer', 'min:1'],
            'effective_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', Rule::in(['goals_completed', 'client_withdrew', 'created_in_error', 'support_completed', 'transferred'])],
            'narrative' => ['nullable', 'string', 'max:10000'],
            'measures' => ['nullable', 'array'],
            'measures.*' => ['required', 'numeric'],
            'follow_up_at' => ['nullable', 'date', 'after:effective_at'],
        ];
    }
}
