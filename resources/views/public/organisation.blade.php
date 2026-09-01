<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="canonical" href="{{ $canonicalUrl }}">
        <title>{{ $organisationName }}</title>
        <style>
            :root { color-scheme: light dark; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.6; }
            body { margin: 0; background: Canvas; color: CanvasText; }
            main { width: min(42rem, calc(100% - 2rem)); margin: 5rem auto; }
            .eyebrow { color: GrayText; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        </style>
    </head>
    <body>
        <main>
            <p class="eyebrow">CommunityKind organisation</p>
            <h1>{{ $organisationName }}</h1>
            <p>This is the verified public home of {{ $organisationName }}.</p>
            <p><a href="{{ $donationUrl }}">Make a simulated donation</a></p>
            <p><a href="{{ $volunteerUrl }}">Volunteer opportunities</a></p>
            <p><a href="{{ $eventsUrl }}">Community events</a></p>
            <p><a href="{{ $inKindUrl }}">Offer goods or materials</a></p>
            @if($impactUrl)<p><a href="{{ $impactUrl }}">Published impact</a></p>@endif
            <p><a href="{{ $sourceUrl }}">Source and licence</a></p>
        </main>
    </body>
</html>
