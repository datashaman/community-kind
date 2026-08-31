<?php

namespace App\Http\Controllers\Organisations;

use App\Http\Controllers\Controller;
use App\Models\Donation;
use App\Models\Organisation;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DonationController extends Controller
{
    public function index(Organisation $currentOrganisation): Response
    {
        Gate::authorize('viewAny', [Donation::class, $currentOrganisation]);

        return Inertia::render('donations/index', [
            'donations' => Donation::query()
                ->with(['party:id,uuid,display_name', 'campaign:id,name', 'fund:id,name', 'mandate:id,donation_id,status'])
                ->withCount('payments')
                ->latest()
                ->paginate(25)
                ->through(fn (Donation $donation): array => [
                    'id' => $donation->id,
                    'supporter' => $donation->party->display_name,
                    'campaign' => $donation->campaign?->name,
                    'fund' => $donation->fund->name,
                    'frequency' => $donation->frequency->value,
                    'amountMinor' => $donation->amount_minor,
                    'currency' => $donation->currency,
                    'source' => $donation->source_code,
                    'mandateStatus' => $donation->mandate?->status->value,
                    'paymentCount' => $donation->payments_count,
                    'createdAt' => $donation->created_at?->toAtomString(),
                ]),
        ]);
    }

    public function show(Organisation $currentOrganisation, string $donation): Response
    {
        $donation = Donation::query()->findOrFail($donation);
        Gate::authorize('view', $donation);
        $donation->load([
            'party:id,uuid,display_name',
            'campaign:id,name',
            'fund:id,name',
            'mandate.events',
            'payments' => fn ($query) => $query->orderBy('attempt_number'),
            'payments.events',
            'payments.refunds',
            'payments.receipt',
        ]);

        return Inertia::render('donations/show', [
            'donation' => [
                'id' => $donation->id,
                'supporter' => $donation->party->display_name,
                'campaign' => $donation->campaign?->name,
                'fund' => $donation->fund->name,
                'frequency' => $donation->frequency->value,
                'amountMinor' => $donation->amount_minor,
                'currency' => $donation->currency,
                'source' => $donation->source_code,
                'createdAt' => $donation->created_at?->toAtomString(),
                'mandate' => $donation->mandate === null ? null : [
                    'id' => $donation->mandate->id,
                    'status' => $donation->mandate->status->value,
                    'providerId' => $donation->mandate->provider_mandate_id,
                    'events' => $donation->mandate->events->map(fn ($event): array => ['from' => $event->from_status->value, 'to' => $event->to_status->value, 'occurredAt' => $event->occurred_at->toAtomString()])->values(),
                ],
                'payments' => $donation->payments->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'attemptNumber' => $payment->attempt_number,
                    'status' => $payment->status->value,
                    'providerId' => $payment->provider_payment_id,
                    'amountMinor' => $payment->amount_minor,
                    'currency' => $payment->currency,
                    'settledAt' => $payment->settled_at?->toAtomString(),
                    'events' => $payment->events->map(fn ($event): array => ['from' => $event->from_status->value, 'to' => $event->to_status->value, 'occurredAt' => $event->occurred_at->toAtomString()])->values(),
                    'refunds' => $payment->refunds->map(fn ($refund): array => ['id' => $refund->id, 'amountMinor' => $refund->amount_minor, 'occurredAt' => $refund->occurred_at->toAtomString()])->values(),
                    'receipt' => $payment->receipt === null ? null : ['number' => $payment->receipt->receipt_number, 'marker' => $payment->receipt->marker, 'issuedAt' => $payment->receipt->issued_at->toAtomString()],
                ])->values(),
            ],
        ]);
    }
}
