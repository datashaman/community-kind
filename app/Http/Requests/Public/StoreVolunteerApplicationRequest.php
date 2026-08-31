<?php

namespace App\Http\Requests\Public;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $required = $this->requiredFields('volunteer_registration');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
            'interests' => [Rule::requiredIf(in_array('interests', $required, true)), 'array', 'max:10'],
            'interests.*' => ['string', 'max:100', 'distinct'],
            'availability' => ['required', 'array', 'min:1', 'max:7'],
            'availability.*' => ['string', 'max:50', 'distinct'],
            'consent_email' => ['required', 'boolean'],
        ];
    }

    /** @return list<string> */
    private function requiredFields(string $form): array
    {
        $configuration = OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::PublicForm)->where('configuration_key', $form)->where('status', OrganisationConfigurationStatus::Active)->latest('version')->first();

        return array_values(array_map('strval', $configuration?->definition['required_fields'] ?? []));
    }
}
