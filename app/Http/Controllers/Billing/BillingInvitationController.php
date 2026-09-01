<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\AcceptBillingInvitation;
use App\Actions\Billing\IssueBillingInvitation;
use App\Enums\BillingAccountRole;
use App\Enums\BillingAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillingInvitationRequest;
use App\Models\BillingAccount;
use App\Models\BillingInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingInvitationController extends Controller
{
    public function store(StoreBillingInvitationRequest $request, BillingAccount $billingAccount, IssueBillingInvitation $issue): RedirectResponse
    {
        $issued = $issue->handle($billingAccount, $request->user(), $request->string('email')->toString(), BillingAccountRole::from($request->string('role')->toString()), $request->boolean('offers_ownership'));
        Inertia::flash('billingInvitationUrl', route('billing-invitations.show', $issued->token));

        return back();
    }

    public function show(Request $request, string $token): Response
    {
        $invitation = BillingInvitation::findByToken($token);
        abort_if($invitation === null || ! $invitation->isPending() || mb_strtolower($request->user()->email) !== $invitation->email || $invitation->billingAccount->status !== BillingAccountStatus::Open, 404);

        return Inertia::render('billing-invitations/accept', ['token' => $token, 'account' => ['legalName' => $invitation->billingAccount->legal_name, 'payerKind' => $invitation->billingAccount->payer_kind->value], 'role' => $invitation->role->value, 'offersOwnership' => $invitation->offers_ownership]);
    }

    public function accept(Request $request, string $token, AcceptBillingInvitation $accept): RedirectResponse
    {
        $invitation = BillingInvitation::findByToken($token);
        abort_if($invitation === null, 404);
        $accept->handle($invitation, $request->user());

        return to_route('billing-accounts.show', $invitation->billing_account_id);
    }
}
