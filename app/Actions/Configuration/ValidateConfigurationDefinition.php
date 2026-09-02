<?php

namespace App\Actions\Configuration;

use App\Enums\OrganisationConfigurationArea;
use App\Enums\SupporterJourneyKind;
use App\Reporting\MetricRegistry;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ValidateConfigurationDefinition
{
    public function __construct(private readonly MetricRegistry $metrics) {}

    /** @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public function handle(OrganisationConfigurationArea $area, array $definition): array
    {
        $rules = match ($area) {
            OrganisationConfigurationArea::PublicForm => [
                'form' => ['required', Rule::in(PublicFormDefinition::purposes())],
                'required_fields' => ['required', 'array', 'min:1'],
                'required_fields.*' => ['required', 'string', 'distinct'],
                'fields' => ['sometimes', 'array', 'min:1'],
                'fields.*.key' => ['required', 'string', 'distinct'],
                'fields.*.type' => ['required', 'string'],
                'fields.*.required' => ['required', 'boolean'],
            ],
            OrganisationConfigurationArea::MessageTemplate => ['channel' => ['required', Rule::in(['email', 'sms'])], 'subject' => ['nullable', 'string', 'max:160'], 'body' => ['required', 'string', 'max:4000'], 'journey_kind' => ['required', Rule::enum(SupporterJourneyKind::class)]],
            OrganisationConfigurationArea::SupporterJourney => ['default_kind' => ['required', Rule::enum(SupporterJourneyKind::class)], 'default_channel' => ['required', Rule::in(['email', 'sms'])], 'default_message_template_id' => ['nullable', 'uuid'], 'require_approval' => ['required', 'accepted'], 'dispatch_rechecks_consent' => ['required', 'accepted'], 'frequency_cap_days' => ['required', 'integer', 'min:'.config('engagement.frequency_cap_days'), 'max:365']],
            OrganisationConfigurationArea::IntakeRules => ['required_fields' => ['required', 'array', 'min:2'], 'required_fields.*' => ['required', Rule::in(['party_uuid', 'email', 'telephone', 'source', 'narrative', 'presenting_needs', 'program_id']), 'distinct'], 'default_urgency' => ['required', Rule::in(['routine', 'priority', 'urgent'])], 'allow_restricted_access_bypass' => ['required', 'declined']],
            OrganisationConfigurationArea::Reporting => ['public_metric_ids' => ['present', 'array'], 'public_metric_ids.*' => ['required', Rule::in($this->metrics->ids()), 'distinct'], 'pack_metric_ids' => ['present', 'array'], 'pack_metric_ids.*' => ['required', Rule::in($this->metrics->ids()), 'distinct']],
        };
        $validator = Validator::make($definition, $rules);
        $validator->after(function ($validator) use ($area, $definition): void {
            /*
             * A destination may be empty: publishing a funder pack without a
             * public page, or the reverse, is an ordinary choice. Publishing
             * nowhere is the one combination that cannot mean anything.
             */
            if ($area === OrganisationConfigurationArea::Reporting) {
                $public = is_array($definition['public_metric_ids'] ?? null) ? $definition['public_metric_ids'] : [];
                $pack = is_array($definition['pack_metric_ids'] ?? null) ? $definition['pack_metric_ids'] : [];
                if ($public === [] && $pack === []) {
                    $validator->errors()->add('public_metric_ids', 'Select at least one metric for the public page or the reporting pack.');
                }
            }
            $requiredFields = is_array($definition['required_fields'] ?? null) ? $definition['required_fields'] : [];
            if ($area === OrganisationConfigurationArea::PublicForm) {
                $required = collect($requiredFields);
                if (! $required->contains('name') || ! $required->contains('email')) {
                    $validator->errors()->add('required_fields', 'Public forms must keep name and email required.');
                }
                if ($required->intersect(['organisation_id', 'party_id', 'status', 'consent_decision'])->isNotEmpty()) {
                    $validator->errors()->add('required_fields', 'System and consent safeguards cannot be configured as form fields.');
                }
                $purpose = is_string($definition['form'] ?? null) ? $definition['form'] : '';
                $allowed = PublicFormDefinition::fieldKeys($purpose);
                if ($required->diff($allowed)->isNotEmpty()) {
                    $validator->errors()->add('required_fields', 'The form contains unsupported fields.');
                }
                if (array_key_exists('fields', $definition) && is_array($definition['fields'])) {
                    $fields = collect($definition['fields']);
                    $fieldKeys = $fields->pluck('key');
                    if ($fieldKeys->count() !== count($allowed) || $fieldKeys->diff($allowed)->isNotEmpty() || collect($allowed)->diff($fieldKeys)->isNotEmpty()) {
                        $validator->errors()->add('fields', 'The form must contain every supported field exactly once.');
                    }
                    $catalogue = collect(PublicFormDefinition::fields($purpose))->keyBy('key');
                    foreach ($fields as $index => $field) {
                        if (! is_array($field) || ! is_string($field['key'] ?? null) || ! $catalogue->has($field['key'])) {
                            continue;
                        }
                        $catalogueField = $catalogue->get($field['key']);
                        if (($field['type'] ?? null) !== $catalogueField['type']) {
                            $validator->errors()->add("fields.{$index}.type", 'The field type cannot be changed.');
                        }
                        $isRequired = filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOL);
                        if ($catalogueField['fixed_required'] && ! $isRequired) {
                            $validator->errors()->add("fields.{$index}.required", 'This field must remain required.');
                        }
                        if ($isRequired !== $required->contains($field['key'])) {
                            $validator->errors()->add("fields.{$index}.required", 'The required state must match the form definition.');
                        }
                    }
                }
            }
            if ($area === OrganisationConfigurationArea::MessageTemplate && ($definition['channel'] ?? null) === 'sms' && mb_strlen((string) ($definition['body'] ?? '')) > 480) {
                $validator->errors()->add('body', 'SMS templates may not exceed 480 characters.');
            }
            if ($area === OrganisationConfigurationArea::MessageTemplate && ($definition['channel'] ?? null) === 'email' && blank($definition['subject'] ?? null)) {
                $validator->errors()->add('subject', 'Email templates require a subject.');
            }
            if ($area === OrganisationConfigurationArea::MessageTemplate) {
                foreach (['subject', 'body'] as $field) {
                    $template = (string) ($definition[$field] ?? '');
                    $remainder = str_replace(['{{ supporter_name }}', '{{ donation_count }}', '{{ activity_frequency }}', '{{ activity_value }}'], '', $template);

                    if (str_contains($remainder, '{{') || str_contains($remainder, '}}')) {
                        $validator->errors()->add($field, 'The template contains an unsupported variable.');
                    }
                }
            }
            if ($area === OrganisationConfigurationArea::IntakeRules && ! collect($requiredFields)->contains('presenting_needs')) {
                $validator->errors()->add('required_fields', 'Intake rules must keep presenting needs required.');
            }
            if ($area === OrganisationConfigurationArea::IntakeRules && ! collect($requiredFields)->contains('party_uuid')) {
                $validator->errors()->add('required_fields', 'Intake rules must keep the Party identity required.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
