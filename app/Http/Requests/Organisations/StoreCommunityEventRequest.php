<?php

namespace App\Http\Requests\Organisations;

use App\Enums\CommunityEventStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommunityEventRequest extends FormRequest
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
        return ['title' => ['required', 'string', 'max:255'], 'summary' => ['required', 'string', 'max:2000'], 'capacity' => ['required', 'integer', 'min:1', 'max:10000'], 'status' => ['required', Rule::enum(CommunityEventStatus::class)->only([CommunityEventStatus::Draft, CommunityEventStatus::Published])], 'registration_opens_at' => ['required', 'date'], 'registration_closes_at' => ['required', 'date', 'after:registration_opens_at'], 'starts_at' => ['required', 'date', 'after_or_equal:registration_closes_at'], 'ends_at' => ['required', 'date', 'after:starts_at']];
    }
}
