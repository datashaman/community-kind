<?php

namespace App\Http\Requests\Organisations;

use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordPartyConsentRequest extends FormRequest
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
            'purpose' => ['required', Rule::enum(ConsentPurpose::class)],
            'decision' => ['required', Rule::enum(ConsentDecision::class)],
            'wording_version' => ['required', 'string', 'max:64'],
            'wording' => ['required', 'string', 'max:2000'],
            'source' => ['required', 'string', 'max:100'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }
}
