<?php

namespace App\Http\Requests\Billing;

use App\Enums\BillingAccountPayerKind;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingAccountRequest extends FormRequest
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
        return ['payer_kind' => ['required', Rule::enum(BillingAccountPayerKind::class)], 'legal_name' => ['required', 'string', 'max:255']];
    }
}
