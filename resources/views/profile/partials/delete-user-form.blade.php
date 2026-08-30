<div x-data="{ open: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }} }">
    <header>
        <h2 class="text-lg font-bold text-loss">Delete account</h2>
        <p class="mt-1 text-sm text-muted">Once deleted, every account, position and snapshot is permanently removed. This can't be undone.</p>
    </header>

    <button type="button" @click="open = true" x-show="!open" class="btn-ghost mt-4 text-loss">Delete account</button>

    <form method="post" action="{{ route('profile.destroy') }}" class="mt-4 space-y-4" x-show="open" x-cloak>
        @csrf
        @method('delete')

        <p class="text-sm text-muted">Enter your password to confirm.</p>
        <input name="password" type="password" class="field" placeholder="Password" autocomplete="current-password">
        <x-input-error :messages="$errors->userDeletion->get('password')" class="text-loss" />

        <div class="flex gap-3">
            <button type="button" @click="open = false" class="btn-ghost">Cancel</button>
            <button class="btn bg-loss text-white hover:bg-loss/90">Delete account</button>
        </div>
    </form>
</div>
