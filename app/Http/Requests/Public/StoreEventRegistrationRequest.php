<?php

namespace App\Http\Requests\Public;

use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->attributes->get('public_organisation') instanceof Organisation;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'], 'consent_email' => ['required', 'boolean']];
    }
}
