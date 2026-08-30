<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class DeleteOrganisationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('delete', $this->route('organisation'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('name') !== $this->organisation()->name) {
                    $validator->errors()->add('name', __('The organisation name does not match.'));
                }
            },
        ];
    }

    /**
     * Get the organisation associated with the request.
     */
    private function organisation(): Organisation
    {
        $organisation = $this->route('organisation');

        abort_if(! $organisation instanceof Organisation, 404);

        return $organisation;
    }
}
