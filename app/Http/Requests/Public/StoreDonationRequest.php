<?php

namespace App\Http\Requests\Public;

use App\Enums\DonationFrequency;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDonationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->attributes->get('public_organisation') instanceof Organisation;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organisation = $this->attributes->get('public_organisation');
        $organisationId = $organisation instanceof Organisation ? $organisation->id : 0;

        return [
            'amount_minor' => ['required', 'integer', Rule::in([2500, 5000, 10000])],
            'frequency' => ['required', Rule::enum(DonationFrequency::class)],
            'fund_id' => ['required', 'integer', Rule::exists('donation_funds', 'id')->where(fn ($query) => $query->where('organisation_id', $organisationId)->where('is_simulated', true))],
            'campaign_id' => ['nullable', 'integer', Rule::exists('fundraising_campaigns', 'id')->where(fn ($query) => $query->where('organisation_id', $organisationId)->where('is_simulated', true))],
            'idempotency_key' => ['required', 'uuid'],
            'name' => ['prohibited'],
            'email' => ['prohibited'],
            'telephone' => ['prohibited'],
            'card_number' => ['prohibited'],
            'bank_account' => ['prohibited'],
        ];
    }
}
