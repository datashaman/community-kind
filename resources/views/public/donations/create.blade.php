<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Simulated donation · {{ $organisation->name }}</title>
        <style>
            :root { color-scheme: light dark; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.6; }
            body { margin: 0; background: Canvas; color: CanvasText; }
            main { width: min(42rem, calc(100% - 2rem)); margin: 3rem auto; }
            .notice { border: 2px solid #b45309; border-radius: .75rem; padding: 1rem; }
            form { display: grid; gap: 1rem; margin-top: 2rem; }
            label { display: grid; gap: .35rem; font-weight: 700; }
            select, button { font: inherit; padding: .7rem; }
            button { cursor: pointer; font-weight: 700; }
            .errors { color: #b91c1c; }
        </style>
    </head>
    <body>
        <main>
            <p><a href="{{ route('public.organisations.show', ['public_organisation' => $organisation->slug]) }}">← {{ $organisation->name }}</a></p>
            <h1>Make a simulated donation</h1>
            <div class="notice" role="note">
                <strong>Demo only—no money will move.</strong>
                Do not enter real names, card numbers, bank details, email addresses, or telephone numbers. This form does not request or transmit them.
            </div>
            @if ($errors->any())
                <div class="errors" role="alert">Please choose valid demo options.</div>
            @endif
            <form method="post" action="{{ route('public.donations.store', ['public_organisation' => $organisation->slug]) }}">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
                <label>Demo amount
                    <select name="amount_minor" required>
                        <option value="2500">ZAR 25.00</option>
                        <option value="5000">ZAR 50.00</option>
                        <option value="10000">ZAR 100.00</option>
                    </select>
                </label>
                <label>Frequency
                    <select name="frequency" required>
                        <option value="one_off">One-off simulation</option>
                        <option value="monthly">Monthly simulation</option>
                    </select>
                </label>
                <label>Demo fund
                    <select name="fund_id" required>
                        @foreach ($funds as $fund)
                            <option value="{{ $fund->id }}">{{ $fund->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Demo campaign
                    <select name="campaign_id">
                        <option value="">No campaign</option>
                        @foreach ($campaigns as $campaign)
                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" @disabled($funds->isEmpty())>Simulate donation</button>
            </form>
        </main>
    </body>
</html>
