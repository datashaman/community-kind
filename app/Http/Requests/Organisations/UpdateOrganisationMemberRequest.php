<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'role_assignments' => ['present', 'array', 'max:20'],
            'role_assignments.*.role' => ['required', 'string', Rule::in(array_column(OrganisationRole::assignable(), 'value'))],
            'role_assignments.*.program_id' => [
                'nullable',
                'integer',
                Rule::exists('programs', 'id')->where('organisation_id', $organisationId),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('role_assignments') && $this->filled('role')) {
            $programIds = $this->input('program_ids', []);
            $assignments = collect(is_array($programIds) && $programIds !== [] ? $programIds : [null])
                ->map(fn (mixed $programId) => [
                    'role' => $this->input('role'),
                    'program_id' => $programId,
                ])
                ->all();

            $this->merge(['role_assignments' => $assignments]);
        }
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('role') && $validator->errors()->has('role_assignments.0.role')) {
                $validator->errors()->add('role', __('The selected role is invalid.'));
            }

            if ($this->has('program_ids')) {
                foreach (array_keys((array) $this->input('program_ids')) as $index) {
                    if ($validator->errors()->has("role_assignments.{$index}.program_id")) {
                        $validator->errors()->add("program_ids.{$index}", __('The selected program is not available for this organisation.'));
                    }
                }
            }

            $organisation = $this->route('organisation');
            $target = $this->route('user');
            $actor = $this->user();

            if (! $organisation instanceof Organisation || ! $target instanceof User || $actor === null) {
                return;
            }

            $assignments = collect((array) $this->input('role_assignments', []));

            if (! $actor->ownsOrganisation($organisation)
                && ($actor->is($target) || $assignments->contains('role', OrganisationRole::OrganisationAdministrator->value))) {
                $validator->errors()->add('role_assignments', __('Organisation administrators cannot alter their own access or appoint another organisation administrator.'));
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
                $validator->errors()->add('role_assignments', __('Each role and scope combination may only be assigned once.'));
            }
        }];
    }

    /**
     * Get the validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role_assignments.*.program_id.exists' => __('The selected program is not available for this organisation.'),
        ];
    }

    /** @return array<int, array{role: string, program_id: int|null}> */
    public function roleAssignments(): array
    {
        return array_map(
            fn (array $assignment): array => [
                'role' => (string) $assignment['role'],
                'program_id' => ($assignment['program_id'] ?? null) === null ? null : (int) $assignment['program_id'],
            ],
            $this->safe()->array('role_assignments'),
        );
    }
}
