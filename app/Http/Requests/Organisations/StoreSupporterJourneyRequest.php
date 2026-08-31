<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupporterJourneyRequest extends FormRequest
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
            'audience_segment_id' => ['required', 'uuid', Rule::exists('audience_segments', 'id')->where('organisation_id', $organisationId)],
            'name' => ['required', 'string', 'max:120', Rule::unique('supporter_journeys', 'name')->where('organisation_id', $organisationId)],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:4000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['subject', 'body'] as $field) {
                $template = (string) $this->input($field);
                $remainder = str_replace(['{{ supporter_name }}', '{{ donation_count }}'], '', $template);

                if (str_contains($remainder, '{{') || str_contains($remainder, '}}')) {
                    $validator->errors()->add($field, 'Only the supporter_name and donation_count placeholders are available.');
                }
            }
        }];
    }
}
