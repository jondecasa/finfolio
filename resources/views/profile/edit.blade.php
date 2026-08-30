<x-layouts.mobile heading="Profile" title="Finfolio · Profile" :back="route('home')">
    <div class="app-pad space-y-6 lg:mx-auto lg:max-w-xl">
        <section class="card">
            @include('profile.partials.update-profile-information-form')
        </section>

        <a href="{{ route('accounts.index') }}" class="card flex items-center justify-between transition hover:bg-ink-700">
            <div>
                <div class="font-semibold">Accounts</div>
                <div class="text-sm text-muted">Add or edit the accounts your positions sit in</div>
            </div>
            <span class="text-muted">&rsaquo;</span>
        </a>

        <section class="card">
            @include('profile.partials.update-password-form')
        </section>

        <section class="card border border-loss/30">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-layouts.mobile>
