<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\ManageBillingAccountMembership;
use App\Enums\BillingAccountRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\UpdateBillingMembershipRequest;
use App\Models\BillingAccount;
use App\Models\BillingAccountMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingMembershipController extends Controller
{
    public function update(UpdateBillingMembershipRequest $request, BillingAccount $billingAccount, BillingAccountMembership $billingMembership, ManageBillingAccountMembership $manage): RedirectResponse
    {
        abort_unless($billingMembership->billing_account_id === $billingAccount->id, 404);
        $manage->update($billingMembership, $request->user(), BillingAccountRole::from($request->string('role')->toString()), $request->boolean('is_owner'));

        return back();
    }

    public function destroy(Request $request, BillingAccount $billingAccount, BillingAccountMembership $billingMembership, ManageBillingAccountMembership $manage): RedirectResponse
    {
        abort_unless($billingMembership->billing_account_id === $billingAccount->id, 404);
        $manage->leave($billingMembership, $request->user());

        return to_route('billing-accounts.index');
    }
}
