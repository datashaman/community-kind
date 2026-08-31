<?php

namespace App\Http\Requests\Organisations;

use App\Enums\PartyBusinessRole;
use App\Enums\PartyKind;
use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartyProfileRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(PartyKind::class)],
            'display_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'program_ids' => ['present', 'array', 'max:20'],
            'program_ids.*' => ['integer', 'distinct', Rule::exists('programs', 'id')->where('organisation_id', $organisationId)],
            'roles' => ['present', 'array', 'max:10'],
            'roles.*' => ['distinct', Rule::enum(PartyBusinessRole::class)],
            'interests' => ['present', 'array', 'max:20'],
            'interests.*' => ['string', 'max:100', 'distinct:ignore_case'],
        ];
    }
}
