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
            'outcome_measures' => ['sometimes', 'array', 'max:20'],
            'outcome_measures.*' => ['array:id,key,label,unit,retired'],
            'outcome_measures.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('program_outcome_measures', 'id')->where(
                    fn ($query) => $query
                        ->where('organisation_id', $organisation instanceof Organisation ? $organisation->id : 0)
                        ->where('program_id', $program instanceof Program ? $program->id : 0),
                ),
            ],
            'outcome_measures.*.key' => ['nullable', 'string', 'max:64'],
            'outcome_measures.*.label' => ['required', 'string', 'max:100'],
            'outcome_measures.*.unit' => ['nullable', 'string', 'max:50'],
            'outcome_measures.*.retired' => ['required', 'boolean'],
            'taxonomies' => ['sometimes', 'array', 'max:20'],
            'taxonomies.*' => ['array:id,key,label,retired,values'],
            'taxonomies.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('program_taxonomies', 'id')->where(
                    fn ($query) => $query
                        ->where('organisation_id', $organisation instanceof Organisation ? $organisation->id : 0)
                        ->where('program_id', $program instanceof Program ? $program->id : 0),
                ),
            ],
            'taxonomies.*.key' => ['nullable', 'string', 'max:64'],
            'taxonomies.*.label' => ['required', 'string', 'max:100'],
            'taxonomies.*.retired' => ['required', 'boolean'],
            'taxonomies.*.values' => ['required', 'array', 'max:50'],
            'taxonomies.*.values.*' => ['array:id,key,label,retired'],
            'taxonomies.*.values.*.id' => [
                'nullable',
                'integer',
                'distinct',
                Rule::exists('program_taxonomy_values', 'id')->where(
                    fn ($query) => $query->where('organisation_id', $organisation instanceof Organisation ? $organisation->id : 0),
                ),
            ],
            'taxonomies.*.values.*.key' => ['nullable', 'string', 'max:64'],
            'taxonomies.*.values.*.label' => ['required', 'string', 'max:100'],
            'taxonomies.*.values.*.retired' => ['required', 'boolean'],
            'configuration' => ['sometimes', 'array:intake_fields,eligibility_fields,risk_flags'],
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
            $identifier = (string) $this->route('program');
            $program = ctype_digit($identifier)
                ? Program::query()->find((int) $identifier)
                : Program::query()->where('slug', $identifier)->first();

            if (! $program instanceof Program) {
                return;
            }

            if ($this->has('stages') && ! $validator->errors()->hasAny(['stages', 'stages.*'])) {
                $stages = $this->items('stages');
                $hasActiveStage = false;

                foreach ($stages as $stage) {
                    if (! (bool) ($stage['retired'] ?? false)) {
                        $hasActiveStage = true;
                    }
                }

                if (! $hasActiveStage) {
                    $validator->errors()->add('stages', 'A Program must have at least one active stage.');
                }

                $this->requireExistingRecords($validator, 'stages', $stages, $this->recordIds($program->stages()->pluck('id')->all()));
            }

            if ($this->has('outcome_measures') && ! $validator->errors()->hasAny(['outcome_measures', 'outcome_measures.*'])) {
                $this->requireExistingRecords(
                    $validator,
                    'outcome_measures',
                    $this->items('outcome_measures'),
                    $this->recordIds($program->outcomeMeasures()->pluck('id')->all()),
                );
            }

            if ($this->has('taxonomies') && ! $validator->errors()->hasAny(['taxonomies', 'taxonomies.*'])) {
                $taxonomies = $this->items('taxonomies');
                $this->requireExistingRecords($validator, 'taxonomies', $taxonomies, $this->recordIds($program->taxonomies()->pluck('id')->all()));

                foreach ($taxonomies as $index => $taxonomy) {
                    if (empty($taxonomy['id'])) {
                        continue;
                    }

                    $existingValueIds = $program->taxonomies()
                        ->whereKey((int) $taxonomy['id'])
                        ->firstOrFail()
                        ->values()
                        ->pluck('id')
                        ->all();
                    $values = $taxonomy['values'] ?? [];
                    $this->requireExistingRecords(
                        $validator,
                        "taxonomies.{$index}.values",
                        is_array($values) ? $this->arrayItems($values) : [],
                        $this->recordIds($existingValueIds),
                    );
                }
            }
        }];
    }

    /**
     * @param  list<array<array-key, mixed>>  $submitted
     * @param  list<int>  $existingIds
     */
    private function requireExistingRecords(Validator $validator, string $field, array $submitted, array $existingIds): void
    {
        $submittedIds = [];

        foreach ($submitted as $item) {
            if (! empty($item['id'])) {
                $submittedIds[] = (int) $item['id'];
            }
        }
        sort($submittedIds);
        sort($existingIds);

        if ($submittedIds !== $existingIds) {
            $validator->errors()->add($field, 'Keep every existing item and retire items that are no longer used.');
        }
    }

    /** @return list<array<array-key, mixed>> */
    private function items(string $field): array
    {
        $items = $this->input($field, []);

        if (! is_array($items)) {
            return [];
        }

        return $this->arrayItems($items);
    }

    /**
     * @param  array<array-key, mixed>  $items
     * @return list<array<array-key, mixed>>
     */
    private function arrayItems(array $items): array
    {
        $result = [];

        foreach ($items as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $ids
     * @return list<int>
     */
    private function recordIds(array $ids): array
    {
        return array_values(array_map(fn (mixed $id): int => (int) $id, $ids));
    }
}
