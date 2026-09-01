<?php

namespace App\Http\Requests\Organisations;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\OrganisationConfigurationStatus;
use App\Enums\SupporterJourneyKind;
use App\Models\AudienceSegment;
use App\Models\Organisation;
use App\Models\OrganisationConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSupporterJourneyRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $defaultConfiguration = OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::SupporterJourney)->where('configuration_key', 'default')->where('status', OrganisationConfigurationStatus::Active)->latest('version')->first();
        $defaults = $defaultConfiguration instanceof OrganisationConfiguration ? $defaultConfiguration->definition : [];
        $requestedTemplateId = $this->input('message_template_id');
        $requestedTemplateKey = $this->input('message_template_key');
        $template = null;
        $usesCustomContent = $requestedTemplateId === '__custom__' || $requestedTemplateKey === '__custom__';
        if (! $usesCustomContent) {
            $templateConfiguration = match (true) {
                is_string($requestedTemplateId) && Str::isUuid($requestedTemplateId) => OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::MessageTemplate)->whereIn('status', [OrganisationConfigurationStatus::Active->value, OrganisationConfigurationStatus::Superseded->value])->find($requestedTemplateId),
                is_string($requestedTemplateKey) && $requestedTemplateKey !== '' => OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::MessageTemplate)->where('configuration_key', $requestedTemplateKey)->where('status', OrganisationConfigurationStatus::Active)->latest('version')->first(),
                is_string($defaults['default_message_template_id'] ?? null) => OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::MessageTemplate)->find($defaults['default_message_template_id']),
                is_string($defaults['default_message_template_key'] ?? null) => OrganisationConfiguration::query()->where('area', OrganisationConfigurationArea::MessageTemplate)->where('configuration_key', $defaults['default_message_template_key'])->where('status', OrganisationConfigurationStatus::Active)->latest('version')->first(),
                default => null,
            };
            $template = $templateConfiguration instanceof OrganisationConfiguration ? $templateConfiguration->definition : null;
        }
        $this->merge([
            'message_template_id' => $usesCustomContent ? null : $requestedTemplateId,
            'message_template_key' => $usesCustomContent ? null : $requestedTemplateKey,
            'journey_kind' => $template['journey_kind'] ?? $this->input('journey_kind', $defaults['default_kind'] ?? SupporterJourneyKind::General->value),
            'channel' => $template['channel'] ?? $this->input('channel', $defaults['default_channel'] ?? 'email'),
            'subject' => $template['subject'] ?? $this->input('subject', ''),
            'body' => $template['body'] ?? $this->input('body'),
            'experiment_enabled' => $this->input('experiment_enabled', false),
        ]);
    }

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
            'message_template_id' => ['bail', 'nullable', 'uuid', Rule::exists('organisation_configurations', 'id')->where(fn ($query) => $query->where('organisation_id', $organisationId)->where('area', OrganisationConfigurationArea::MessageTemplate->value)->whereIn('status', [OrganisationConfigurationStatus::Active->value, OrganisationConfigurationStatus::Superseded->value]))],
            'message_template_key' => ['nullable', 'string', 'max:100', Rule::exists('organisation_configurations', 'configuration_key')->where(fn ($query) => $query->where('organisation_id', $organisationId)->where('area', OrganisationConfigurationArea::MessageTemplate->value)->where('status', OrganisationConfigurationStatus::Active->value))],
            'name' => ['required', 'string', 'max:120', Rule::unique('supporter_journeys', 'name')->where('organisation_id', $organisationId)],
            'journey_kind' => ['required', Rule::enum(SupporterJourneyKind::class)],
            'channel' => ['required', Rule::in(['email', 'sms'])],
            'subject' => ['nullable', 'string', 'max:160', 'required_if:channel,email'],
            'body' => ['required', 'string', 'max:4000'],
            'experiment_enabled' => ['required', 'boolean'],
            'variant_subject' => ['nullable', 'string', 'max:160', 'required_if:experiment_enabled,1'],
            'variant_body' => ['nullable', 'string', 'max:4000', 'required_if:experiment_enabled,1'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['subject', 'body', 'variant_subject', 'variant_body'] as $field) {
                $template = (string) $this->input($field);
                $remainder = str_replace(['{{ supporter_name }}', '{{ donation_count }}', '{{ activity_frequency }}', '{{ activity_value }}'], '', $template);

                if (str_contains($remainder, '{{') || str_contains($remainder, '}}')) {
                    $validator->errors()->add($field, 'Only supporter_name, donation_count, activity_frequency, and activity_value placeholders are available.');
                }
            }
            if ($this->input('channel') === 'sms' && (mb_strlen((string) $this->input('body')) > 480 || mb_strlen((string) $this->input('variant_body')) > 480)) {
                $validator->errors()->add('body', 'SMS journey messages may not exceed 480 characters.');
            }
            $organisation = $this->route('current_organisation');
            if ($organisation instanceof Organisation) {
                $segment = AudienceSegment::query()->where('organisation_id', $organisation->id)->find($this->input('audience_segment_id'));
                if ($segment !== null && ($segment->criteria['channel'] ?? null) !== $this->input('channel')) {
                    $validator->errors()->add('channel', 'Journey channel must match the saved audience consent channel.');
                }
            }
        }];
    }
}
