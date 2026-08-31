<?php

namespace App\Http\Requests\Organisations;

use App\Enums\PartnerCommitmentStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartnerCommitmentRequest extends FormRequest
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
        return ['title' => ['required', 'string', 'max:255'], 'details' => ['required', 'string', 'max:2000'], 'status' => ['required', Rule::enum(PartnerCommitmentStatus::class)], 'due_on' => ['nullable', 'date']];
    }
}
