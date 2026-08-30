<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganisationMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organisation = $this->route('organisation');
        $organisationId = $organisation instanceof Organisation ? $organisation->id : 0;

        return [
            'role' => ['required', 'string', Rule::in(array_column(OrganisationRole::assignable(), 'value'))],
            'program_ids' => ['sometimes', 'array'],
            'program_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('programs', 'id')->where('organisation_id', $organisationId),
            ],
        ];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'program_ids.*.exists' => __('The selected program is not available for this organisation.'),
        ];
    }
}
