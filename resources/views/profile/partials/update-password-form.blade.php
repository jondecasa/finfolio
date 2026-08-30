<header>
    <h2 class="text-lg font-bold">Password</h2>
    <p class="mt-1 text-sm text-muted">Use a long, random password to keep your account secure.</p>
</header>

<form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-4">
    @csrf
    @method('put')

    <div>
        <label for="update_password_current_password" class="mb-1.5 block text-sm font-semibold text-muted">Current password</label>
        <input id="update_password_current_password" name="current_password" type="password" class="field" autocomplete="current-password">
        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-loss" />
    </div>

    <div>
        <label for="update_password_password" class="mb-1.5 block text-sm font-semibold text-muted">New password</label>
        <input id="update_password_password" name="password" type="password" class="field" autocomplete="new-password">
        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-loss" />
    </div>

    <div>
        <label for="update_password_password_confirmation" class="mb-1.5 block text-sm font-semibold text-muted">Confirm password</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="field" autocomplete="new-password">
        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-loss" />
    </div>

    <div class="flex items-center gap-4">
        <button class="btn-primary">Save</button>
        @if (session('status') === 'password-updated')
            <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="text-sm text-gain">Saved.</p>
        @endif
    </div>
</form>
