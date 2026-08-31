<?php

namespace App\Http\Requests\Organisations;

use App\Enums\VolunteerAssignmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionVolunteerAssignmentRequest extends FormRequest
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
            'status' => ['required', Rule::enum(VolunteerAssignmentStatus::class)],
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440', 'required_if:status,attended'],
        ];
    }
}
