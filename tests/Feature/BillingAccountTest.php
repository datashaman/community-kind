<?php

use App\Actions\Billing\AcceptBillingInvitation;
use App\Actions\Billing\CloseBillingAccount;
use App\Actions\Billing\CreateBillingAccount;
use App\Actions\Billing\IssueBillingInvitation;
use App\Actions\Billing\ManageBillingAccountMembership;
use App\Actions\Billing\ManageBillingContact;
use App\Enums\BillingAccountPayerKind;
use App\Enums\BillingAccountRole;
use App\Enums\BillingAccountStatus;
use App\Enums\OrganisationRole;
use App\Enums\SubscriptionStatus;
use App\Models\BillingAccountEvent;
use App\Models\Organisation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('creates provider-independent individual and organisation payers without tenant authority', function (BillingAccountPayerKind $kind) {
    $creator = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $account = app(CreateBillingAccount::class)->handle($creator, $kind, 'Regional Foundation');
    $membership = $account->memberships()->sole();

    expect($account->payer_kind)->toBe($kind)
        ->and($account->id)->toBeString()->not->toBe('Regional Foundation')
        ->and($membership->user_id)->toBe($creator->id)
        ->and($membership->is_owner)->toBeTrue()
        ->and($membership->role)->toBe(BillingAccountRole::Administrator)
        ->and($organisation->memberships()->where('user_id', $creator->id)->exists())->toBeFalse();
})->with(BillingAccountPayerKind::cases());

it('creates membership only on invitation acceptance and keeps viewers and contacts non-authoritative', function () {
    $owner = User::factory()->create();
    $administrator = User::factory()->create();
    $viewer = User::factory()->create();
    $account = app(CreateBillingAccount::class)->handle($owner, BillingAccountPayerKind::Organisation, 'Safe Payer');
    $adminInvitation = app(IssueBillingInvitation::class)->handle($account, $owner, $administrator->email, BillingAccountRole::Administrator, false);
    $viewerInvitation = app(IssueBillingInvitation::class)->handle($account, $owner, $viewer->email, BillingAccountRole::Viewer, false);
    expect($account->memberships()->count())->toBe(1);
    app(AcceptBillingInvitation::class)->handle($adminInvitation->invitation, $administrator);
    $this->actingAs($viewer)->get(route('billing-invitations.show', $viewerInvitation->token))->assertOk();
    expect($account->memberships()->count())->toBe(2);
    $this->actingAs($viewer)->post(route('billing-invitations.accept', $viewerInvitation->token))->assertRedirect();

    $contact = app(ManageBillingContact::class)->create($account, $administrator, 'Accounts Payable', 'billing@example.test', ['invoice', 'renewal']);
    expect($account->memberships()->count())->toBe(3)
        ->and($contact->email)->toBe('billing@example.test')
        ->and(User::query()->where('email', $contact->email)->exists())->toBeFalse()
        ->and(fn () => app(ManageBillingContact::class)->create($account, $viewer, 'No authority', 'none@example.test', ['invoice']))->toThrow(ValidationException::class);

    $this->actingAs($viewer)->get(route('billing-accounts.show', $account))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('billing-accounts/show')->has('organisations', 1)->missing('parties')->missing('subscriptions'));
    app(ManageBillingContact::class)->remove($contact, $administrator);
    $viewerMembership = $account->memberships()->where('user_id', $viewer->id)->sole();
    app(ManageBillingAccountMembership::class)->leave($viewerMembership, $owner);
    expect($contact->refresh()->removed_at)->not->toBeNull()
        ->and($viewerMembership->refresh()->ended_at)->not->toBeNull();
});

it('keeps ownership independently accepted and prevents the last owner from leaving', function () {
    $owner = User::factory()->create();
    $successor = User::factory()->create();
    $account = app(CreateBillingAccount::class)->handle($owner, BillingAccountPayerKind::Individual, 'Original Owner');
    $ownerMembership = $account->memberships()->sole();
    expect(fn () => app(ManageBillingAccountMembership::class)->leave($ownerMembership, $owner))->toThrow(ValidationException::class);

    $invitation = app(IssueBillingInvitation::class)->handle($account, $owner, $successor->email, BillingAccountRole::Viewer, true);
    expect($account->memberships()->where('user_id', $successor->id)->exists())->toBeFalse();
    app(AcceptBillingInvitation::class)->handle($invitation->invitation, $successor);
    app(ManageBillingAccountMembership::class)->leave($ownerMembership, $owner);
    $nextOwner = User::factory()->create();
    $successorInvitation = app(IssueBillingInvitation::class)->handle($account, $successor, $nextOwner->email, BillingAccountRole::Viewer, true);

    expect($ownerMembership->refresh()->ended_at)->not->toBeNull()
        ->and($account->memberships()->where('user_id', $successor->id)->where('is_owner', true)->exists())->toBeTrue();
    $this->actingAs($successor)->get(route('billing-accounts.show', $account))
        ->assertInertia(fn (Assert $page) => $page->has('invitations', 1)->where('invitations.0.id', $successorInvitation->invitation->id));
});

it('blocks closure for current subscriptions and closes without changing the funded organisation', function () {
    $owner = User::factory()->create();
    $organisationOwner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create(['user_id' => $organisationOwner->id, 'role' => OrganisationRole::OrganisationAdministrator, 'is_owner' => true]);
    $account = app(CreateBillingAccount::class)->handle($owner, BillingAccountPayerKind::Organisation, 'Closing Payer');
    $subscription = Subscription::factory()->create(['billing_account_id' => $account->id, 'organisation_id' => $organisation->id]);

    expect(fn () => app(CloseBillingAccount::class)->handle($account, $owner))->toThrow(ValidationException::class);
    $subscription->update(['ends_at' => now()->addDay()]);
    expect(fn () => app(CloseBillingAccount::class)->handle($account, $owner))->toThrow(ValidationException::class);
    $subscription->update(['status' => SubscriptionStatus::Ended, 'ends_at' => now(), 'current_marker' => null]);
    app(CloseBillingAccount::class)->handle($account, $owner);

    expect($account->refresh()->status)->toBe(BillingAccountStatus::Closed)
        ->and($organisation->fresh()->status->value)->toBe('active')
        ->and($organisation->memberships()->where('user_id', $organisationOwner->id)->exists())->toBeTrue()
        ->and(BillingAccountEvent::query()->where('billing_account_id', $account->id)->where('type', 'account_closed')->exists())->toBeTrue()
        ->and(fn () => Subscription::factory()->create(['billing_account_id' => $account->id]))->toThrow(LogicException::class)
        ->and(fn () => app(IssueBillingInvitation::class)->handle($account->fresh(), $owner, 'later@example.test', BillingAccountRole::Viewer, false))->toThrow(ValidationException::class);
});

it('requires a verified User to establish a Billing Account', function () {
    $user = User::factory()->unverified()->create();

    expect(fn () => app(CreateBillingAccount::class)->handle($user, BillingAccountPayerKind::Individual, 'Unverified payer'))
        ->toThrow(ValidationException::class);
});
