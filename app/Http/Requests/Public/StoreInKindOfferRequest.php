<?php

namespace App\Http\Requests\Public;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInKindOfferRequest extends FormRequest
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
        $configuration = OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::PublicForm)->where('configuration_key', 'in_kind_offer')->where('status', OrganisationConfigurationStatus::Active)->latest('version')->first();
        $required = array_values(array_map('strval', $configuration?->definition['required_fields'] ?? []));

        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'], 'category' => ['required', 'string', 'max:100'], 'description' => ['required', 'string', 'max:2000'], 'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'], 'unit' => ['required', 'string', 'max:50'], 'estimated_value_minor' => [Rule::requiredIf(in_array('estimated_value_minor', $required, true)), 'nullable', 'integer', 'min:0', 'max:1000000000', 'required_with:currency'], 'currency' => [Rule::requiredIf(in_array('currency', $required, true)), 'nullable', 'string', 'size:3', 'required_with:estimated_value_minor'], 'condition' => ['required', 'string', 'max:100'], 'consent_email' => ['required', 'boolean']];
    }
}
