<?php

namespace App\Http\Requests\Organisations;

use App\Enums\CaseClassification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreCaseDocumentRequest extends FormRequest
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
            'document' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png'])->max((int) config('case_documents.max_bytes') / 1024),
            ],
            'classification' => ['required', Rule::enum(CaseClassification::class)],
        ];
    }
}
