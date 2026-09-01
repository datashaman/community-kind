<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\SupporterJourneyKind;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMessageTemplateRequest extends FormRequest
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
            'template_key' => ['nullable', 'string', 'max:100', 'alpha_dash:ascii', Rule::exists('organisation_configurations', 'configuration_key')->where(fn ($query) => $query->where('organisation_id', $organisationId)->where('area', OrganisationConfigurationArea::MessageTemplate->value))],
            'name' => ['required', 'string', 'max:100'],
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:160', 'required_if:channel,email'],
            'body' => ['required', 'string', 'max:4000'],
            'journey_kind' => ['required', Rule::enum(SupporterJourneyKind::class)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (Str::of($this->string('name')->toString())->ascii()->slug()->isEmpty()) {
                $validator->errors()->add('name', 'The template name must include letters or numbers.');
            }

            foreach (['subject', 'body'] as $field) {
                $template = (string) $this->input($field);
                $remainder = str_replace(['{{ supporter_name }}', '{{ donation_count }}', '{{ activity_frequency }}', '{{ activity_value }}'], '', $template);

                if (str_contains($remainder, '{{') || str_contains($remainder, '}}')) {
                    $validator->errors()->add($field, 'Only supporter_name, donation_count, activity_frequency, and activity_value variables are available.');
                }
            }

            if ($this->input('channel') === 'sms' && mb_strlen((string) $this->input('body')) > 480) {
                $validator->errors()->add('body', 'SMS templates may not exceed 480 characters.');
            }
        }];
    }
}
