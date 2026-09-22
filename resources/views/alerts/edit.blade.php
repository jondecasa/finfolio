@php
    $asset = $alert->asset;
    $num = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
@endphp

<x-layouts.mobile heading="Edit alert" title="Finfolio · Edit alert" :back="route('alerts.index')">
  <div class="app-pad lg:mx-auto lg:max-w-xl">
    <div class="mb-5 flex items-center gap-3 rounded-2xl bg-ink-700 p-3">
        <span class="logo-bubble">
            @if ($asset->logo_url)
                <img src="{{ $asset->logo_url }}" alt="" class="h-full w-full object-cover">
            @else
                {{ \Illuminate\Support\Str::substr($asset->symbol, 0, 3) }}
            @endif
        </span>
        <div class="min-w-0 flex-1">
            <div class="truncate font-semibold">{{ $asset->name }}</div>
            <div class="text-xs text-muted">
                {{ $asset->symbol }}
                @if ($asset->current_price !== null)
                    · now {{ \App\Support\Money::format($asset->current_price, $asset->currency ?? 'USD') }}
                @endif
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('alerts.update', $alert) }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Notify me when the price is…</label>
            <div class="flex gap-2" x-data="{ condition: @js(old('condition', $alert->condition)) }">
                <button type="button" @click="condition = 'above'" class="tab flex-1" :class="condition === 'above' ? 'tab-active' : ''">Above</button>
                <button type="button" @click="condition = 'below'" class="tab flex-1" :class="condition === 'below' ? 'tab-active' : ''">Below</button>
                <input type="hidden" name="condition" :value="condition">
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">
                Target price <span class="text-muted/60">({{ $asset->currency ?? 'USD' }})</span>
            </label>
            <input type="number" step="any" min="0" class="field" name="target_price"
                   value="{{ old('target_price', $num($alert->target_price)) }}" inputmode="decimal" placeholder="0.00">
        </div>

        @unless ($alert->active)
            <p class="text-xs text-muted">This alert already triggered — saving will re-arm it.</p>
        @endunless

        <button class="btn-primary w-full">Save changes</button>
    </form>

    <div class="mt-3">
        <x-confirm-form
            :action="route('alerts.destroy', $alert)"
            title="Delete this alert?"
            :message="'The alert on '.$asset->name.' will be removed.'"
            confirm="Delete"
            trigger="Delete alert"
            trigger-class="btn-ghost w-full text-loss" />
    </div>
  </div>
</x-layouts.mobile>
