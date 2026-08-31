<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offer goods · {{ $organisation->name }}</title>
    <style>:root{color-scheme:light dark;font-family:ui-sans-serif,system-ui,sans-serif;line-height:1.6}main{width:min(42rem,calc(100% - 2rem));margin:3rem auto}form,label{display:grid;gap:.5rem}form{gap:1rem}input,textarea,select,button{font:inherit;padding:.7rem}.errors{color:#b91c1c}</style>
</head>
<body>
<main>
    <p><a href="{{ route('public.organisations.show', $organisation->slug) }}">← {{ $organisation->name }}</a></p>
    <h1>Offer goods or materials</h1>
    @if($errors->any())
        <div class="errors" role="alert">
            <p>Please correct the offer details:</p>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    <form method="post" action="{{ route('public.in-kind.store', $organisation->slug) }}">
        @csrf
        <label>Name<input name="name" value="{{ old('name') }}" required autocomplete="name"></label>
        <label>Email<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label>
        <label>Category<input name="category" value="{{ old('category') }}" required placeholder="Food, clothing, equipment..."></label>
        <label>Description<textarea name="description" required>{{ old('description') }}</textarea></label>
        <label>Quantity<input name="quantity" type="number" step="0.01" min="0.01" value="{{ old('quantity') }}" required></label>
        <label>Unit<input name="unit" value="{{ old('unit') }}" required placeholder="boxes, items, kilograms"></label>
        <label>Condition<select name="condition"><option value="new" @selected(old('condition', 'new') === 'new')>New</option><option value="good_used" @selected(old('condition') === 'good_used')>Good used condition</option><option value="requires_assessment" @selected(old('condition') === 'requires_assessment')>Requires assessment</option></select></label>
        <label>Estimated value in minor units (optional)<input name="estimated_value_minor" type="number" min="0" value="{{ old('estimated_value_minor') }}"></label>
        <label>Currency (optional)<input name="currency" maxlength="3" value="{{ old('currency') }}" placeholder="ZAR"></label>
        <input type="hidden" name="consent_email" value="0">
        <label><input type="checkbox" name="consent_email" value="1" @checked(old('consent_email'))> Email me fulfilment follow-up and supporter updates</label>
        <button type="submit">Record offer</button>
    </form>
</main>
</body>
</html>
