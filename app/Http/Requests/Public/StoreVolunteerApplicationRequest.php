<?php

namespace App\Http\Requests\Public;

use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVolunteerApplicationRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
            'interests' => ['sometimes', 'array', 'max:10'],
            'interests.*' => ['string', 'max:100', 'distinct'],
            'availability' => ['required', 'array', 'min:1', 'max:7'],
            'availability.*' => ['string', 'max:50', 'distinct'],
            'consent_email' => ['required', 'boolean'],
        ];
    }
}
