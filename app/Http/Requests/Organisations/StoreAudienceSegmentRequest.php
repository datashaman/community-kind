<?php

namespace App\Http\Requests\Organisations;

use App\Enums\ConsentChannel;
use App\Enums\ConsentPurpose;
use App\Enums\PartyBusinessRole;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAudienceSegmentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120', Rule::unique('audience_segments', 'name')->where('organisation_id', $organisationId)],
            'purpose' => ['required', Rule::in([ConsentPurpose::SupporterUpdates->value])],
            'channel' => ['required', Rule::in([ConsentChannel::Email->value, ConsentChannel::Sms->value, ConsentChannel::Telephone->value])],
            'role' => ['required', Rule::in([PartyBusinessRole::Donor->value, PartyBusinessRole::Volunteer->value, PartyBusinessRole::PartnerContact->value, PartyBusinessRole::EventAttendee->value])],
            'service_area' => ['nullable', 'string', 'max:100', Rule::exists('party_addresses', 'service_area')->where('organisation_id', $organisationId)],
            'interest' => ['nullable', 'string', 'max:100', Rule::exists('party_interests', 'slug')->where('organisation_id', $organisationId)],
            'donation_activity' => ['required', 'boolean'],
            'campaign_source' => ['nullable', 'string', 'max:64', Rule::exists('donations', 'source_code')->where('organisation_id', $organisationId)],
        ];
    }
}
