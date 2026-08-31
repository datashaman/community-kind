<?php

namespace App\Http\Requests;

use App\Enums\PartyBusinessRole;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DashboardMetricsRequest extends FormRequest
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
            'period_start' => ['nullable', 'date_format:Y-m-d'],
            'period_end' => ['nullable', 'date_format:Y-m-d'],
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->where('organisation_id', $organisationId)],
            'area' => ['nullable', 'string', 'max:100', Rule::exists('party_addresses', 'service_area')->where('organisation_id', $organisationId)],
            'location' => ['nullable', 'string', 'size:2', Rule::exists('party_addresses', 'country_code')->where('organisation_id', $organisationId)],
            'cohort' => ['nullable', Rule::enum(PartyBusinessRole::class)],
            'campaign_id' => ['nullable', 'integer', Rule::exists('fundraising_campaigns', 'id')->where('organisation_id', $organisationId)],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('period_start') xor $this->filled('period_end')) {
                $validator->errors()->add('period_end', 'Both period boundaries are required.');
            }

            if ($this->filled('period_start') && $this->filled('period_end') && $this->date('period_start')->gte($this->date('period_end'))) {
                $validator->errors()->add('period_end', 'The exclusive period end must be after the period start.');
            }

            if ($this->filled('period_start') && $this->filled('period_end') && $this->date('period_start')->diffInDays($this->date('period_end')) > 366) {
                $validator->errors()->add('period_end', 'Reporting periods cannot exceed 366 days.');
            }

        }];
    }
}
