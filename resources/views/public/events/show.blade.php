<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $event->title }} · {{ $organisation->name }}</title>
    <style>:root{color-scheme:light dark;font-family:ui-sans-serif,system-ui,sans-serif;line-height:1.6}main{width:min(42rem,calc(100% - 2rem));margin:3rem auto}form,label{display:grid;gap:.5rem}form{gap:1rem}input,button{font:inherit;padding:.7rem}.errors{color:#b91c1c}</style>
</head>
<body>
<main>
    <p><a href="{{ route('public.events.index', $organisation->slug) }}">← Events</a></p>
    <h1>{{ $event->title }}</h1>
    <p>{{ $event->summary }}</p>
    <p>{{ $event->starts_at->toDayDateTimeString() }}</p>
    @if($errors->any())
        <div class="errors" role="alert">Please correct the highlighted registration details.</div>
    @endif
    <form method="post" action="{{ route('public.events.store', [$organisation->slug, $event]) }}">
        @csrf
        <label>Name<input name="name" value="{{ old('name') }}" required autocomplete="name">@error('name')<span>{{ $message }}</span>@enderror</label>
        <label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email">@error('email')<span>{{ $message }}</span>@enderror</label>
        <input type="hidden" name="consent_email" value="0">
        <label><input type="checkbox" name="consent_email" value="1" @checked(old('consent_email'))> Email me event follow-up and supporter updates</label>
        <button type="submit" @disabled(!$event->acceptsRegistrations())>Register</button>
    </form>
</main>
</body>
</html>
