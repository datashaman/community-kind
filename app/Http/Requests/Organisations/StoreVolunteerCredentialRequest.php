<?php

namespace App\Http\Requests\Organisations;

use App\Enums\VolunteerCredentialStatus;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerCredentialRequest extends FormRequest
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
        $organisation = $this->route('current_organisation');
        abort_unless($organisation instanceof Organisation, 404);

        return [
            'type' => ['required', 'string', 'max:100', Rule::unique('volunteer_credentials', 'type')->where('organisation_id', $organisation->id)->where('volunteer_application_id', (string) $this->route('application'))],
            'status' => ['required', Rule::enum(VolunteerCredentialStatus::class)->only([VolunteerCredentialStatus::Pending, VolunteerCredentialStatus::Verified])],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ];
    }
}
