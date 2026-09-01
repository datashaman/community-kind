<?php

namespace App\Http\Requests\Organisations;

use App\Reporting\MetricRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportingPublicationRequest extends FormRequest
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
            'public_metric_ids' => ['required', 'array', 'min:1'],
            'public_metric_ids.*' => ['required', 'string', Rule::in(app(MetricRegistry::class)->ids()), 'distinct'],
            'pack_metric_ids' => ['required', 'array', 'min:1'],
            'pack_metric_ids.*' => ['required', 'string', Rule::in(app(MetricRegistry::class)->ids()), 'distinct'],
        ];
    }
}
