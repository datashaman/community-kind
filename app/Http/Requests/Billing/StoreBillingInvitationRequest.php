<?php

namespace App\Http\Requests\Billing;

use App\Enums\BillingAccountRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBillingInvitationRequest extends FormRequest
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
        return ['email' => ['required', 'email:rfc', 'max:255'], 'role' => ['required', Rule::enum(BillingAccountRole::class)], 'offers_ownership' => ['required', 'boolean']];
    }
}
