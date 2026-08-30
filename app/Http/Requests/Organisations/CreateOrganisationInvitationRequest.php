<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Party;
use App\Rules\UniqueOrganisationInvitation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'person_party_id' => [
                'nullable',
                'integer',
                Rule::prohibitedIf(fn () => $this->filled('new_person_name')),
                Rule::exists(Party::class, 'id')
                    ->where('organisation_id', $organisation->id)
                    ->where('kind', 'person'),
            ],
            'new_person_name' => ['nullable', 'required_without:person_party_id', 'string', 'max:255'],
            'role_assignments' => ['required', 'array', 'min:1', 'max:20'],
            'role_assignments.*.role' => ['required', 'string', Rule::in(array_column(OrganisationRole::assignable(), 'value'))],
            'role_assignments.*.program_id' => [
                'nullable',
                'integer',
                Rule::exists('programs', 'id')->where('organisation_id', $organisation->id),
            ],
            'offers_ownership' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('role_assignments') && $this->filled('role')) {
            $this->merge([
                'role_assignments' => [[
                    'role' => $this->input('role'),
                    'program_id' => null,
                ]],
            ]);
        }

    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('role') && $validator->errors()->has('role_assignments.0.role')) {
                $validator->errors()->add('role', __('The selected role is invalid.'));
            }

            $organisation = $this->route('organisation');

            if (! $organisation instanceof Organisation || $this->user() === null) {
                return;
            }

            $assignments = collect((array) $this->input('role_assignments', []));
            $offersOwnership = $this->boolean('offers_ownership');

            if (! $this->user()->ownsOrganisation($organisation)
                && ($offersOwnership || $assignments->contains('role', OrganisationRole::OrganisationAdministrator->value))) {
                $validator->errors()->add('role_assignments', __('Only an organisation owner can appoint another owner or organisation administrator.'));
            }

            if ($assignments->contains(fn (mixed $assignment) => is_array($assignment)
                && ($assignment['role'] ?? null) === OrganisationRole::OrganisationAdministrator->value
                && ($assignment['program_id'] ?? null) !== null)) {
                $validator->errors()->add('role_assignments', __('Organisation administrator must have organisation-wide scope.'));
            }

            $keys = $assignments->map(fn (mixed $assignment) => is_array($assignment)
                ? ($assignment['role'] ?? '').':'.($assignment['program_id'] ?? 'organisation')
                : '');

            if ($keys->duplicates()->isNotEmpty()) {
                $validator->errors()->add('role_assignments', __('Each role and scope combination may only be proposed once.'));
            }
        }];
    }
}
