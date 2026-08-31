<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCaseRequest extends FormRequest
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
        $organisationId = $organisation instanceof Organisation ? $organisation->id : 0;

        return [
            'membership_id' => ['required', 'integer', Rule::exists('organisation_members', 'id')->where('organisation_id', $organisationId)->whereNull('ended_at')],
            'reason' => ['nullable', Rule::in(['initial_assignment', 'caseload_transfer', 'availability', 'program_rebalance', 'other'])],
        ];
    }
}
