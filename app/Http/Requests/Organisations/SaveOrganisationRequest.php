<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use App\Rules\OrganisationName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveOrganisationRequest extends FormRequest
{
    /**
     * Determine whether the user is authorised to make this request.
     */
    public function authorize(): bool
    {
        if ($this->routeIs('organisations.store')) {
            return $this->user()?->can('create', Organisation::class) ?? false;
        }

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
            'name' => ['required', 'string', 'max:255', new OrganisationName],
        ];
    }
}
