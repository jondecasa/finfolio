<x-guest-layout>
    <h2 class="mb-5 text-lg font-bold">Create your account</h2>

    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <div>
            <label for="name" class="mb-1.5 block text-sm font-semibold text-muted">Name</label>
            <input id="name" class="field" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-loss" />
        </div>

        <div>
            <label for="email" class="mb-1.5 block text-sm font-semibold text-muted">Email</label>
            <input id="email" class="field" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-loss" />
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-semibold text-muted">Password</label>
            <input id="password" class="field" type="password" name="password" required autocomplete="new-password">
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-loss" />
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-muted">Confirm password</label>
            <input id="password_confirmation" class="field" type="password" name="password_confirmation" required autocomplete="new-password">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-loss" />
        </div>

        <button class="btn-primary w-full">Register</button>

        <p class="pt-1 text-center text-sm text-muted">
            Already registered?
            <a class="font-semibold text-white" href="{{ route('login') }}">Log in</a>
        </p>
    </form>
</x-guest-layout>
