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
        /*
         * Each destination requires only that it is sent, not that it holds
         * something. Requiring `min:1` meant an Organisation could not publish
         * a funder pack without also publishing a public page — and could not
         * withdraw the last metric it had published either. An empty
         * publication means nothing may leave the dashboard, which is a state
         * an Organisation has to be able to reach.
         */
        return [
            'public_metric_ids' => ['present', 'array'],
            'public_metric_ids.*' => ['required', 'string', Rule::in(app(MetricRegistry::class)->ids()), 'distinct'],
            'pack_metric_ids' => ['present', 'array'],
            'pack_metric_ids.*' => ['required', 'string', Rule::in(app(MetricRegistry::class)->ids()), 'distinct'],
        ];
    }
}
