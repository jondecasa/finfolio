<x-guest-layout>
    <x-auth-session-status class="mb-4 text-sm text-gain" :status="session('status')" />

    <h2 class="mb-5 text-lg font-bold">Log in</h2>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-muted">Email</label>
            <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-loss" />
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-semibold text-muted">Password</label>
            <input id="password" class="field" type="password" name="password" required autocomplete="current-password">
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-loss" />
        </div>

        <label for="remember_me" class="flex items-center gap-2 text-sm text-muted">
            <input id="remember_me" type="checkbox" class="rounded border-ink-500 bg-ink-700 text-accent focus:ring-accent" name="remember">
            {{ __('Remember me') }}
        </label>

        <button class="btn-primary w-full">Log in</button>

        <div class="flex items-center justify-between pt-1 text-sm text-muted">
            @if (Route::has('password.request'))
                <a class="hover:text-white" href="{{ route('password.request') }}">Forgot password?</a>
            @endif
            <a class="hover:text-white" href="{{ route('register') }}">Create account</a>
        </div>
    </form>
</x-guest-layout>
