<?php

namespace App\Http\Requests\Organisations;

use App\Enums\CaseClassification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReclassifyServiceCaseRequest extends FormRequest
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
            'classification' => ['required', Rule::enum(CaseClassification::class)],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
