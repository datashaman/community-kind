<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Demo receipt · {{ $organisation->name }}</title>
        <style>
            :root { color-scheme: light dark; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.6; }
            body { margin: 0; background: Canvas; color: CanvasText; }
            main { width: min(42rem, calc(100% - 2rem)); margin: 3rem auto; }
            .receipt { border: 3px dashed #b45309; border-radius: .75rem; padding: 1.5rem; }
            dt { font-weight: 700; } dd { margin: 0 0 1rem; }
        </style>
    </head>
    <body>
        <main>
            <p><a href="{{ route('public.organisations.show', ['public_organisation' => $organisation->slug]) }}">← {{ $organisation->name }}</a></p>
            <h1>Simulation complete</h1>
            <p>No money moved and no person or payment details were collected or contacted.</p>
            @php($payment = $donation->payments->last())
            @php($receipt = $payment?->receipt)
            <section class="receipt" aria-label="Demo receipt">
                <h2>{{ $receipt?->marker ?? 'Demo—No receipt issued' }}</h2>
                <dl>
                    <dt>Simulation transaction</dt><dd>{{ $donation->id }}</dd>
                    <dt>Status</dt><dd>{{ str($payment?->status?->value ?? 'not_processed')->replace('_', ' ')->title() }}</dd>
                    <dt>Demo amount</dt><dd>{{ $donation->currency }} {{ number_format($donation->amount_minor / 100, 2) }}</dd>
                    <dt>Frequency</dt><dd>{{ str($donation->frequency->value)->replace('_', ' ')->title() }}</dd>
                    <dt>Demo fund</dt><dd>{{ $donation->fund->name }}</dd>
                    <dt>Demo campaign</dt><dd>{{ $donation->campaign?->name ?? 'None' }}</dd>
                    @if ($receipt)
                        <dt>Demo receipt number</dt><dd>{{ $receipt->receipt_number }}</dd>
                    @endif
                </dl>
            </section>
        </main>
    </body>
</html>
