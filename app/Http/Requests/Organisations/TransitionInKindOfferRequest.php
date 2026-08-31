<?php

namespace App\Http\Requests\Organisations;

use App\Enums\InKindOfferStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionInKindOfferRequest extends FormRequest
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
        return ['status' => ['required', Rule::enum(InKindOfferStatus::class)], 'fulfilment_outcome' => ['nullable', 'string', 'max:2000', 'required_if:status,fulfilled,unable_to_fulfil']];
    }
}
