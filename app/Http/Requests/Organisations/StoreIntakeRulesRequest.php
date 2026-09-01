<?php

namespace App\Http\Requests\Organisations;

use App\Enums\IntakeUrgency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIntakeRulesRequest extends FormRequest
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
            'required_contact_fields' => ['present', 'array', 'max:2'],
            'required_contact_fields.*' => ['string', Rule::in(['email', 'telephone']), 'distinct'],
            'default_urgency' => ['required', Rule::enum(IntakeUrgency::class)],
            'allow_restricted_access_bypass' => ['required', 'declined'],
        ];
    }
}
