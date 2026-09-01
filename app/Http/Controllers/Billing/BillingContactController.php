<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ManageBillingContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillingContactRequest;
use App\Models\BillingAccount;
use App\Models\BillingContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingContactController extends Controller
{
    public function store(StoreBillingContactRequest $request, BillingAccount $billingAccount, ManageBillingContact $manage): RedirectResponse
    {
        $manage->create($billingAccount, $request->user(), $request->string('name')->toString(), $request->string('email')->toString(), array_values(array_map(strval(...), $request->array('purposes'))));

        return back();
    }

    public function destroy(Request $request, BillingAccount $billingAccount, BillingContact $billingContact, ManageBillingContact $manage): RedirectResponse
    {
        abort_unless($billingContact->billing_account_id === $billingAccount->id, 404);
        $manage->remove($billingContact, $request->user());

        return back();
    }
}
