<?php

namespace App\Http\Requests\Organisations;

use App\Enums\SupporterJourneyStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionSupporterJourneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([SupporterJourneyStatus::Approved->value, SupporterJourneyStatus::Scheduled->value, SupporterJourneyStatus::Paused->value])],
            'scheduled_for' => ['nullable', 'date', 'after:now', 'required_if:status,scheduled'],
        ];
    }
}
