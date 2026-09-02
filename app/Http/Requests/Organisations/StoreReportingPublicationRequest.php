<?php

namespace App\Http\Requests\Organisations;

use App\Reporting\MetricRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        /*
         * Each destination requires only that it is sent, not that it holds
         * something. Requiring `min:1` on both meant an Organisation could not
         * publish a funder pack without also publishing a public page, or the
         * reverse — a restriction nothing in the product asks for.
         */
        return [
            'public_metric_ids' => ['present', 'array'],
            'public_metric_ids.*' => ['required', 'string', Rule::in(app(MetricRegistry::class)->ids()), 'distinct'],
            'pack_metric_ids' => ['present', 'array'],
            'pack_metric_ids.*' => ['required', 'string', Rule::in(app(MetricRegistry::class)->ids()), 'distinct'],
        ];
    }

    /**
     * A version that publishes nothing anywhere is the one combination that
     * cannot mean anything, so that is the only one refused.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->array('public_metric_ids') === [] && $this->array('pack_metric_ids') === []) {
                    $validator->errors()->add('public_metric_ids', 'Select at least one metric for the public page or the reporting pack.');
                }
            },
        ];
    }
}
