@php
    $currencyOptions = ['EUR', 'USD', 'GBP', 'CHF', 'JPY', 'CAD', 'AUD', 'SEK', 'NOK', 'DKK', 'PLN'];
@endphp

<header>
    <h2 class="text-lg font-bold">Profile</h2>
    <p class="mt-1 text-sm text-muted">Update your name and the currency everything is shown in.</p>
</header>

<form method="post" action="{{ route('profile.update') }}" class="mt-5 space-y-4">
    @csrf
    @method('patch')

    <div>
        <label for="name" class="mb-1.5 block text-sm font-semibold text-muted">Name</label>
        <input id="name" name="name" type="text" class="field" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name">
        <x-input-error class="mt-2 text-loss" :messages="$errors->get('name')" />
    </div>

    <div>
        <label class="mb-1.5 block text-sm font-semibold text-muted">Email</label>
        <input type="email" class="field opacity-60" value="{{ $user->email }}" disabled readonly>
        <p class="mt-1 text-xs text-muted">Your email address can't be changed.</p>
    </div>

    <div>
        <label for="base_currency" class="mb-1.5 block text-sm font-semibold text-muted">Base currency</label>
        <select id="base_currency" name="base_currency" class="field">
            @foreach ($currencyOptions as $c)
                <option value="{{ $c }}" @selected($user->currency() === $c)>{{ $c }}</option>
            @endforeach
        </select>
        <x-input-error class="mt-2 text-loss" :messages="$errors->get('base_currency')" />
    </div>

    <div class="flex items-center gap-4">
        <button class="btn-primary">Save</button>
        @if (session('status') === 'profile-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gain">Saved.</p>
        @endif
    </div>
</form>
