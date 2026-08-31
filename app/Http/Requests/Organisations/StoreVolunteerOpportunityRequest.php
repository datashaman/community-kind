<?php

namespace App\Http\Requests\Organisations;

use App\Enums\VolunteerOpportunityStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerOpportunityRequest extends FormRequest
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
        return [
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:2000'],
            'interest_tags' => ['present', 'array', 'max:10'],
            'interest_tags.*' => ['string', 'max:100', 'distinct'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::enum(VolunteerOpportunityStatus::class)->only([VolunteerOpportunityStatus::Draft, VolunteerOpportunityStatus::Published])],
            'registration_opens_at' => ['required', 'date'],
            'registration_closes_at' => ['required', 'date', 'after:registration_opens_at'],
            'starts_at' => ['nullable', 'date', 'after_or_equal:registration_closes_at'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
    }
}
