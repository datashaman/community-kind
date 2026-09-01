<?php

namespace App\Http\Requests\Organisations;

use App\Actions\Configuration\PublicFormDefinition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePublicFormRequest extends FormRequest
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
            'form' => ['required', Rule::in(PublicFormDefinition::purposes())],
            'ordered_fields' => ['required', 'array', 'min:1'],
            'ordered_fields.*' => ['required', 'string', 'distinct'],
            'required_fields' => ['present', 'array'],
            'required_fields.*' => ['required', 'string', 'distinct'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function ($validator): void {
            $purpose = $this->string('form')->toString();
            $allowed = collect(PublicFormDefinition::fieldKeys($purpose));
            $ordered = collect(array_values(array_filter($this->array('ordered_fields'), is_string(...))));
            $required = collect(array_values(array_filter($this->array('required_fields'), is_string(...))));

            if ($ordered->count() !== $allowed->count() || $ordered->diff($allowed)->isNotEmpty() || $allowed->diff($ordered)->isNotEmpty()) {
                $validator->errors()->add('ordered_fields', 'Keep every supported field in the form.');
            }
            if ($required->diff($allowed)->isNotEmpty()) {
                $validator->errors()->add('required_fields', 'The form contains an unsupported required field.');
            }
        }];
    }
}
