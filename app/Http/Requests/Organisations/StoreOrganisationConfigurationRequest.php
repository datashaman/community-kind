<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationConfigurationArea;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganisationConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'area' => ['required', Rule::enum(OrganisationConfigurationArea::class), Rule::notIn([OrganisationConfigurationArea::Reporting->value, OrganisationConfigurationArea::PublicForm->value, OrganisationConfigurationArea::MessageTemplate->value, OrganisationConfigurationArea::SupporterJourney->value, OrganisationConfigurationArea::IntakeRules->value])],
            'configuration_key' => ['required', 'string', 'alpha_dash:ascii', 'max:100'],
            'definition_json' => ['required', 'string', 'json', 'max:20000'],
        ];
    }
}
