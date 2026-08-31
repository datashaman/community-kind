<?php

namespace App\Http\Requests\Organisations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaseItemRequest extends FormRequest
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
        $kind = $this->string('kind')->toString();
        $requiredFor = fn (string ...$kinds): bool => in_array($kind, $kinds, true);

        return [
            'kind' => ['required', Rule::in(['goal', 'service', 'referral', 'task', 'appointment', 'interaction', 'note'])],
            'title' => [Rule::requiredIf(fn (): bool => $requiredFor('goal', 'task')), 'nullable', 'string', 'max:200'],
            'description' => [Rule::requiredIf(fn (): bool => $requiredFor('goal', 'task')), 'nullable', 'string', 'max:5000'],
            'summary' => [Rule::requiredIf(fn (): bool => $requiredFor('service', 'appointment', 'interaction')), 'nullable', 'string', 'max:5000'],
            'content' => [Rule::requiredIf(fn (): bool => $requiredFor('note')), 'nullable', 'string', 'max:20000'],
            'service_code' => [Rule::requiredIf(fn (): bool => $requiredFor('service')), 'nullable', 'string', 'regex:/^[a-z0-9_\-]+$/', 'max:64'],
            'destination' => [Rule::requiredIf(fn (): bool => $requiredFor('referral')), 'nullable', 'string', 'max:500'],
            'purpose' => [Rule::requiredIf(fn (): bool => $requiredFor('referral')), 'nullable', 'string', 'max:1000'],
            'minimum_necessary' => [Rule::requiredIf(fn (): bool => $requiredFor('referral')), 'nullable', 'string', 'max:2000'],
            'sharing_authority' => [Rule::requiredIf(fn (): bool => $requiredFor('referral')), 'nullable', Rule::in(['service_consent'])],
            'location' => [Rule::requiredIf(fn (): bool => $requiredFor('appointment')), 'nullable', 'string', 'max:500'],
            'interaction_type' => [Rule::requiredIf(fn (): bool => $requiredFor('interaction')), 'nullable', Rule::in(['in_person', 'telephone', 'email', 'video', 'other'])],
            'target_at' => ['nullable', 'date'],
            'scheduled_for' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date'],
            'scheduled_at' => [Rule::requiredIf(fn (): bool => $requiredFor('appointment')), 'nullable', 'date'],
            'occurred_at' => [Rule::requiredIf(fn (): bool => $requiredFor('interaction')), 'nullable', 'date'],
            'corrects_note_id' => ['nullable', 'uuid'],
        ];
    }
}
