<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Source and licence — {{ config('app.name') }}</title>
        <style>
            :root { color-scheme: light dark; font-family: ui-sans-serif, system-ui, sans-serif; line-height: 1.6; }
            body { margin: 0; background: Canvas; color: CanvasText; }
            main { width: min(42rem, calc(100% - 2rem)); margin: 4rem auto; }
            a { color: LinkText; text-underline-offset: .2em; }
            code { overflow-wrap: anywhere; }
            .notice { padding: 1rem; border: 1px solid GrayText; border-radius: .5rem; }
        </style>
    </head>
    <body>
        <main>
            <p><a href="{{ route('home') }}">← CommunityKind</a></p>
            <h1>Source and licence</h1>
            <p class="notice">
                CommunityKind is provided without warranty. You may use, study,
                modify, and redistribute the software under the GNU Affero General
                Public License version 3.0 only.
            </p>
            <h2>Running release</h2>
            <p><code>{{ $release }}</code></p>
            <p>
                <a href="{{ $release === 'development' ? $repository : $repository.'/tree/'.$release }}">
                    View the corresponding source
                </a>
            </p>
            <h2>Licence text</h2>
            <p><a href="{{ route('source-and-licence.license') }}">Read the complete AGPL-3.0-only licence</a>.</p>
            <h2>Other materials</h2>
            <p>
                Documentation and original non-brand visual assets are licensed
                under CC BY-SA 4.0. Deliberately synthetic datasets are dedicated
                under CC0 1.0. Third-party components retain their own licences.
                Project names and marks are governed separately.
            </p>
        </main>
    </body>
</html>
