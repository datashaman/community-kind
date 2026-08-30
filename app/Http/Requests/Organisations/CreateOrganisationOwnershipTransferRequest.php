<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrganisationOwnershipTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('transferOwnership', $this->route('organisation')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organisation = $this->route('organisation');
        $organisationId = $organisation instanceof Organisation ? $organisation->id : 0;

        return [
            'nominee_user_id' => [
                'required',
                'integer',
                Rule::exists('organisation_members', 'user_id')
                    ->where('organisation_id', $organisationId)
                    ->whereNull('ended_at'),
            ],
        ];
    }
}
