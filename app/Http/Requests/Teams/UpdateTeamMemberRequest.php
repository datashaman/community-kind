<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');
        $teamId = $team instanceof Team ? $team->id : 0;

        return [
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
            'program_ids' => ['sometimes', 'array'],
            'program_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('programs', 'id')->where('team_id', $teamId),
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
            'program_ids.*.exists' => __('The selected program is not available for this team.'),
        ];
    }
}
