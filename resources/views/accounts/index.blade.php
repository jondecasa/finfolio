@php
    $hidden = auth()->user()->values_hidden;
@endphp

<x-layouts.mobile heading="Accounts" title="Finfolio · Accounts" :back="route('home')">
    <div class="app-pad lg:mx-auto lg:max-w-xl">

        {{-- Existing accounts --}}
        <div class="space-y-2">
            @foreach ($accounts as $account)
                @php $row = $valueByAccount->get($account->id); @endphp
                <div class="card" x-data="{ editing: false }">
                    <div class="flex items-center gap-3">
                        <span class="logo-bubble bg-ink-600">{{ \Illuminate\Support\Str::substr($account->name, 0, 1) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold">{{ $account->name }}</div>
                            <div class="text-xs text-muted">
                                {{ ucfirst($account->type) }} · {{ strtoupper($account->currency) }}
                                · {{ $account->holdings_count }} {{ \Illuminate\Support\Str::plural('position', $account->holdings_count) }}
                            </div>
                        </div>
                        <div class="text-right">
                            <x-money :amount="$row['value'] ?? 0" :currency="$currency" :hidden="$hidden" class="block text-sm font-semibold" />
                            <button type="button" class="text-xs text-muted hover:text-white" @click="editing = ! editing"
                                    x-text="editing ? 'Close' : 'Edit'"></button>
                        </div>
                    </div>

                    <div x-show="editing" x-cloak class="mt-4 space-y-3 border-t border-white/5 pt-4">
                        <form method="POST" action="{{ route('accounts.update', $account) }}" class="space-y-3">
                            @csrf
                            @method('PUT')
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-muted">Name</label>
                                <input type="text" name="name" class="field" value="{{ $account->name }}" required maxlength="60">
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-muted">Type</label>
                                    <select name="type" class="field">
                                        @foreach ($types as $t)
                                            <option value="{{ $t }}" @selected($account->type === $t)>{{ ucfirst($t) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-muted">Currency</label>
                                    <select name="currency" class="field">
                                        @foreach ($currencyOptions as $c)
                                            <option value="{{ $c }}" @selected(strtoupper($account->currency) === $c)>{{ $c }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <button class="btn-primary w-full !py-2.5">Save</button>
                        </form>

                        @if ($accounts->count() > 1)
                            @php $pc = $account->holdings_count; @endphp
                            <x-confirm-form
                                :action="route('accounts.destroy', $account)"
                                title="Delete this account?"
                                :message="$pc > 0
                                    ? 'Deleting “'.$account->name.'” also removes its '.$pc.' '.\Illuminate\Support\Str::plural('position', $pc).'. This can’t be undone.'
                                    : 'Deleting “'.$account->name.'” can’t be undone.'"
                                confirm="Delete account"
                                :trigger="$pc > 0 ? 'Delete account & '.$pc.' '.\Illuminate\Support\Str::plural('position', $pc) : 'Delete account'"
                            />
                        @else
                            <p class="text-center text-xs text-muted">This is your only account, so it can't be deleted.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        {{-- New account --}}
        <div class="mt-6">
            <h2 class="mb-3 text-lg font-bold">New account</h2>
            <form method="POST" action="{{ route('accounts.store') }}" class="card space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-semibold text-muted">Name</label>
                    <input type="text" name="name" class="field" value="{{ old('name') }}" placeholder="e.g. Retirement" required maxlength="60">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-muted">Type</label>
                        <select name="type" class="field">
                            @foreach ($types as $t)
                                <option value="{{ $t }}" @selected(old('type') === $t)>{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-muted">Currency</label>
                        <select name="currency" class="field">
                            @foreach ($currencyOptions as $c)
                                <option value="{{ $c }}" @selected(old('currency', auth()->user()->currency()) === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <button class="btn-primary w-full">Add account</button>
            </form>
        </div>
    </div>
</x-layouts.mobile>
