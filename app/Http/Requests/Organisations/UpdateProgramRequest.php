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
            'configuration' => ['sometimes', 'array:labels,stages,outcome_measures,taxonomies,intake_fields,eligibility_fields,risk_flags'],
            'configuration.labels' => ['required_with:configuration', 'array:request,case'],
            'configuration.labels.request' => ['required_with:configuration', 'string', 'max:100'],
            'configuration.labels.case' => ['required_with:configuration', 'string', 'max:100'],
            'configuration.stages' => ['required_with:configuration', 'array', 'max:20'],
            'configuration.stages.*' => ['array:key,label'],
            'configuration.stages.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'configuration.stages.*.label' => ['required', 'string', 'max:100'],
            'configuration.outcome_measures' => ['required_with:configuration', 'array', 'max:20'],
            'configuration.outcome_measures.*' => ['array:key,label,unit'],
            'configuration.outcome_measures.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'configuration.outcome_measures.*.label' => ['required', 'string', 'max:100'],
            'configuration.outcome_measures.*.unit' => ['nullable', 'string', 'max:50'],
            'configuration.taxonomies' => ['required_with:configuration', 'array', 'max:20'],
            'configuration.taxonomies.*' => ['array:key,label,values'],
            'configuration.taxonomies.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'configuration.taxonomies.*.label' => ['required', 'string', 'max:100'],
            'configuration.taxonomies.*.values' => ['required', 'array', 'max:50'],
            'configuration.taxonomies.*.values.*' => ['required', 'string', 'max:100', 'distinct'],
            'configuration.intake_fields' => ['sometimes', 'array', 'max:30'],
            'configuration.intake_fields.*' => ['array:key,label,type,required'],
            'configuration.intake_fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'configuration.intake_fields.*.label' => ['required', 'string', 'max:100'],
            'configuration.intake_fields.*.type' => ['required', Rule::in(['text', 'textarea', 'boolean', 'date'])],
            'configuration.intake_fields.*.required' => ['required', 'boolean'],
            'configuration.eligibility_fields' => ['sometimes', 'array', 'max:20'],
            'configuration.eligibility_fields.*' => ['array:key,label'],
            'configuration.eligibility_fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'configuration.eligibility_fields.*.label' => ['required', 'string', 'max:100'],
            'configuration.risk_flags' => ['sometimes', 'array', 'max:20'],
            'configuration.risk_flags.*' => ['array:key,label'],
            'configuration.risk_flags.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'configuration.risk_flags.*.label' => ['required', 'string', 'max:100'],
        ];
    }
}
