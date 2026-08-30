<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramRequest extends FormRequest
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
        $organisation = $this->route('organisation');
        $identifier = (string) $this->route('program');
        $program = ctype_digit($identifier)
            ? Program::query()->find((int) $identifier)
            : Program::query()->where('slug', $identifier)->first();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/',
                Rule::unique('programs', 'slug')
                    ->where('organisation_id', $organisation instanceof Organisation ? $organisation->id : 0)
                    ->ignore($program?->id),
            ],
        ];
    }
}
