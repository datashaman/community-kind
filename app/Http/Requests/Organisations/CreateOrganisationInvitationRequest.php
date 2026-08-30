<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Rules\UniqueOrganisationInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrganisationInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organisation = $this->route('organisation');

        abort_if(! $organisation instanceof Organisation, 404);

        return [
            'email' => ['required', 'string', 'email', 'max:255', new UniqueOrganisationInvitation($organisation)],
            'role' => ['required', 'string', Rule::in(array_column(OrganisationRole::assignable(), 'value'))],
        ];
    }
}
