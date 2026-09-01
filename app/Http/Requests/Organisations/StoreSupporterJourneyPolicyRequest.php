<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\SupporterJourneyKind;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupporterJourneyPolicyRequest extends FormRequest
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
            'default_kind' => ['required', Rule::enum(SupporterJourneyKind::class)],
            'default_channel' => ['required', Rule::in(['email', 'sms'])],
            'default_message_template_id' => ['bail', 'nullable', 'uuid', Rule::exists('organisation_configurations', 'id')->where(fn ($query) => $query->where('organisation_id', $organisationId)->where('area', OrganisationConfigurationArea::MessageTemplate->value)->whereIn('status', [OrganisationConfigurationStatus::Active->value, OrganisationConfigurationStatus::Superseded->value]))],
            'require_approval' => ['required', 'accepted'],
            'dispatch_rechecks_consent' => ['required', 'accepted'],
            'frequency_cap_days' => ['required', 'integer', 'min:'.config('engagement.frequency_cap_days'), 'max:365'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->filled('default_message_template_id') || $validator->errors()->has('default_message_template_id')) {
                return;
            }

            $template = OrganisationConfiguration::query()
                ->where('area', OrganisationConfigurationArea::MessageTemplate)
                ->whereIn('status', [OrganisationConfigurationStatus::Active->value, OrganisationConfigurationStatus::Superseded->value])
                ->find($this->string('default_message_template_id')->toString());

            if ($template instanceof OrganisationConfiguration
                && ($template->definition['channel'] !== $this->input('default_channel')
                    || $template->definition['journey_kind'] !== $this->input('default_kind'))) {
                $validator->errors()->add('default_message_template_id', 'The template must match the selected default channel and journey kind.');
            }
        }];
    }
}
