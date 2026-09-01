<?php

namespace App\Http\Requests\Organisations;

use App\Enums\AudienceActivityType;
use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\PartyBusinessRole;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAudienceSegmentRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('activity_type')) {
            $requiresDonation = $this->boolean('donation_activity') || filled($this->input('campaign_source'));
            $this->merge([
                'activity_type' => $requiresDonation ? AudienceActivityType::Donation->value : AudienceActivityType::Any->value,
                'minimum_frequency' => $requiresDonation ? 1 : 0,
            ]);
        }
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
            'name' => ['required', 'string', 'max:120', Rule::unique('audience_segments', 'name')->where('organisation_id', $organisationId)],
            'purpose' => ['required', Rule::in([ConsentPurpose::SupporterUpdates->value])],
            'channel' => ['required', Rule::in([ConsentChannel::Email->value, ConsentChannel::Sms->value, ConsentChannel::Telephone->value])],
            'role' => ['required', Rule::in([PartyBusinessRole::Donor->value, PartyBusinessRole::Volunteer->value, PartyBusinessRole::PartnerContact->value, PartyBusinessRole::EventAttendee->value])],
            'service_area' => ['nullable', 'string', 'max:100', Rule::exists('party_addresses', 'service_area')->where('organisation_id', $organisationId)],
            'interest' => ['nullable', 'string', 'max:100', Rule::exists('party_interests', 'slug')->where('organisation_id', $organisationId)],
            'donation_activity' => ['required', 'boolean'],
            'campaign_source' => ['nullable', 'string', 'max:64', Rule::exists('donations', 'source_code')->where('organisation_id', $organisationId)],
            'activity_type' => ['required', Rule::enum(AudienceActivityType::class)],
            'recency_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'minimum_frequency' => ['required', 'integer', 'min:0', 'max:10000'],
            'minimum_value' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $activity = $this->input('activity_type');
            if ($activity === AudienceActivityType::Any->value && filled($this->input('minimum_value'))) {
                $validator->errors()->add('minimum_value', 'Any-activity segments cannot compare values with different units.');
            }
            if (filled($this->input('campaign_source')) && ! in_array($activity, [AudienceActivityType::Donation->value, AudienceActivityType::Any->value], true)) {
                $validator->errors()->add('campaign_source', 'Donation source is only available for donation activity.');
            }
        }];
    }
}
