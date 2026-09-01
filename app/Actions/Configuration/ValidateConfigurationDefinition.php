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
            OrganisationConfigurationArea::PublicForm => ['form' => ['required', Rule::in(['event_registration', 'volunteer_registration', 'in_kind_offer', 'supporter_profile'])], 'required_fields' => ['required', 'array', 'min:1'], 'required_fields.*' => ['required', 'string', 'distinct']],
            OrganisationConfigurationArea::MessageTemplate => ['channel' => ['required', Rule::in(['email', 'sms'])], 'subject' => ['nullable', 'string', 'max:160'], 'body' => ['required', 'string', 'max:4000'], 'journey_kind' => ['required', Rule::enum(SupporterJourneyKind::class)]],
            OrganisationConfigurationArea::SupporterJourney => ['default_kind' => ['required', Rule::enum(SupporterJourneyKind::class)], 'default_channel' => ['required', Rule::in(['email', 'sms'])], 'default_message_template_id' => ['nullable', 'uuid'], 'require_approval' => ['required', 'accepted'], 'dispatch_rechecks_consent' => ['required', 'accepted'], 'frequency_cap_days' => ['required', 'integer', 'min:'.config('engagement.frequency_cap_days'), 'max:365']],
            OrganisationConfigurationArea::IntakeRules => ['required_fields' => ['required', 'array', 'min:2'], 'required_fields.*' => ['required', Rule::in(['party_uuid', 'email', 'telephone', 'source', 'narrative', 'presenting_needs', 'program_id']), 'distinct'], 'default_urgency' => ['required', Rule::in(['routine', 'priority', 'urgent'])], 'allow_restricted_access_bypass' => ['required', 'declined']],
            OrganisationConfigurationArea::Reporting => ['public_metric_ids' => ['required', 'array'], 'public_metric_ids.*' => ['required', Rule::in($this->metrics->ids()), 'distinct'], 'pack_metric_ids' => ['required', 'array', 'min:1'], 'pack_metric_ids.*' => ['required', Rule::in($this->metrics->ids()), 'distinct']],
        };
        $validator = Validator::make($definition, $rules);
        $validator->after(function ($validator) use ($area, $definition): void {
            $requiredFields = is_array($definition['required_fields'] ?? null) ? $definition['required_fields'] : [];
            if ($area === OrganisationConfigurationArea::PublicForm) {
                $required = collect($requiredFields);
                if (! $required->contains('name') || ! $required->contains('email')) {
                    $validator->errors()->add('required_fields', 'Public forms must keep name and email required.');
                }
                if ($required->intersect(['organisation_id', 'party_id', 'status', 'consent_decision'])->isNotEmpty()) {
                    $validator->errors()->add('required_fields', 'System and consent safeguards cannot be configured as form fields.');
                }
                $allowed = match ($definition['form'] ?? null) {
                    'event_registration' => ['name', 'email'],
                    'volunteer_registration' => ['name', 'email', 'interests', 'availability'],
                    'in_kind_offer' => ['name', 'email', 'category', 'description', 'quantity', 'unit', 'estimated_value_minor', 'currency', 'condition'],
                    'supporter_profile' => ['name', 'email', 'telephone'],
                    default => [],
                };
                if ($required->diff($allowed)->isNotEmpty()) {
                    $validator->errors()->add('required_fields', 'The form contains unsupported fields.');
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
