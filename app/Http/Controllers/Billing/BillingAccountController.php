<?php

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\CloseBillingAccount;
use App\Actions\Billing\CreateBillingAccount;
use App\Enums\BillingAccountPayerKind;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreBillingAccountRequest;
use App\Models\BillingAccount;
use App\Models\BillingAccountMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingAccountController extends Controller
{
    public function index(Request $request): Response
    {
        $memberships = $request->user()->billingAccountMemberships()->whereNull('ended_at')->with('billingAccount')->get();

        return Inertia::render('billing-accounts/index', ['accounts' => $memberships->map(fn (BillingAccountMembership $membership): array => ['id' => $membership->billingAccount->id, 'legalName' => $membership->billingAccount->legal_name, 'payerKind' => $membership->billingAccount->payer_kind->value, 'status' => $membership->billingAccount->status->value, 'role' => $membership->role->value, 'isOwner' => $membership->is_owner])]);
    }

    public function store(StoreBillingAccountRequest $request, CreateBillingAccount $create): RedirectResponse
    {
        $account = $create->handle($request->user(), BillingAccountPayerKind::from($request->string('payer_kind')->toString()), $request->string('legal_name')->toString());

        return to_route('billing-accounts.show', $account);
    }

    public function show(Request $request, BillingAccount $billingAccount): Response
    {
        Gate::authorize('view', $billingAccount);
        $own = $billingAccount->memberships()->where('user_id', $request->user()->id)->whereNull('ended_at')->firstOrFail();

        return Inertia::render('billing-accounts/show', [
            'account' => ['id' => $billingAccount->id, 'legalName' => $billingAccount->legal_name, 'payerKind' => $billingAccount->payer_kind->value, 'status' => $billingAccount->status->value],
            'membership' => ['id' => $own->id, 'role' => $own->role->value, 'isOwner' => $own->is_owner],
            'members' => $billingAccount->memberships()->whereNull('ended_at')->with('user')->get()->map(fn (BillingAccountMembership $membership): array => ['id' => $membership->id, 'name' => $membership->user->name, 'email' => $membership->user->email, 'role' => $membership->role->value, 'isOwner' => $membership->is_owner]),
            'contacts' => $billingAccount->contacts()->whereNull('removed_at')->get(['id', 'name', 'email', 'purposes']),
            'invitations' => $own->role->value === 'administrator' || $own->is_owner ? $billingAccount->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now())->get(['id', 'email', 'role', 'offers_ownership', 'expires_at']) : [],
            'currentSubscriptionCount' => $billingAccount->subscriptions()->where('status', '!=', SubscriptionStatus::Ended)->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))->count(),
        ]);
    }

    public function destroy(Request $request, BillingAccount $billingAccount, CloseBillingAccount $close): RedirectResponse
    {
        Gate::authorize('close', $billingAccount);
        $close->handle($billingAccount, $request->user());

        return back();
    }
}
