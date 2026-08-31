<?php

namespace App\Http\Requests\Organisations;

use App\Http\Requests\DashboardMetricsRequest;
use Illuminate\Validation\Rule;

class StoreImpactSnapshotRequest extends DashboardMetricsRequest
{
    public function rules(): array
    {
        return ['audience' => ['required', Rule::in(['board', 'funder', 'public'])], ...parent::rules()];
    }
}
