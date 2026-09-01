<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use App\Models\Program;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'request_label' => ['sometimes', 'required', 'string', 'max:100'],
            'case_label' => ['sometimes', 'required', 'string', 'max:100'],
            'stages' => ['sometimes', 'required', 'array', 'min:1', 'max:20'],
            'stages.*' => ['array:id,key,label,retired'],
            'stages.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('program_stages', 'id')->where(
                    fn ($query) => $query
                        ->where('organisation_id', $organisation instanceof Organisation ? $organisation->id : 0)
                        ->where('program_id', $program instanceof Program ? $program->id : 0),
                ),
            ],
            'stages.*.key' => ['nullable', 'string', 'max:64'],
            'stages.*.label' => ['required', 'string', 'max:100'],
            'stages.*.retired' => ['required', 'boolean'],
            'configuration' => ['sometimes', 'array:outcome_measures,taxonomies,intake_fields,eligibility_fields,risk_flags'],
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

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->has('stages') || $validator->errors()->hasAny(['stages', 'stages.*'])) {
                return;
            }

            $stageInput = $this->input('stages', []);
            /** @var list<array{id?: mixed, retired?: mixed}> $stages */
            $stages = is_array($stageInput) ? $stageInput : [];

            if (collect($stages)->every(fn (array $stage): bool => (bool) ($stage['retired'] ?? false))) {
                $validator->errors()->add('stages', 'A Program must have at least one active stage.');
            }

            $identifier = (string) $this->route('program');
            $program = ctype_digit($identifier)
                ? Program::query()->find((int) $identifier)
                : Program::query()->where('slug', $identifier)->first();
            $submittedIds = collect($stages)
                ->pluck('id')
                ->filter()
                ->map(fn (mixed $id): int => (int) $id)
                ->sort()
                ->values();
            $existingIds = $program instanceof Program ? $program->stages()->pluck('id')->sort()->values() : null;

            if ($existingIds !== null && $submittedIds->all() !== $existingIds->all()) {
                $validator->errors()->add('stages', 'Keep every existing stage in the pathway and retire stages that are no longer used.');
            }
        }];
    }
}
