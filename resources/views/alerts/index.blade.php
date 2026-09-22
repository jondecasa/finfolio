@php
    $num = fn ($v, $ccy) => \App\Support\Money::format((float) $v, $ccy ?: 'USD');
@endphp

<x-layouts.mobile heading="Alerts" title="Finfolio · Alerts">
    <div class="app-pad lg:mx-auto lg:max-w-2xl">
        <div class="mb-4">
            <a href="{{ route('alerts.create') }}" class="btn-primary flex w-full items-center justify-center gap-1.5">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                New alert
            </a>
        </div>

        @unless ($telegramConfigured)
            <p class="mb-4 text-xs text-muted">
                No Telegram bot is set up on this server yet, but you can still enable
                <a href="{{ route('profile.edit') }}" class="underline">browser push notifications</a> from your profile.
            </p>
        @else
            @unless ($telegramLinked)
                <div class="card mb-4">
                    <p class="font-semibold">Connect Telegram to get notified</p>
                    <p class="mt-1 text-sm text-muted">
                        Open this link and press <strong class="text-white">Start</strong> in Telegram, then come back and check —
                        otherwise a triggered alert has nowhere to notify you.
                    </p>
                    <a href="{{ $telegramLinkUrl }}" target="_blank" rel="noopener noreferrer"
                       class="btn-primary mt-3 flex items-center justify-center gap-1.5">
                        Open Telegram
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
                    </a>
                    <form method="POST" action="{{ route('notifications.telegram.check') }}" class="mt-2">
                        @csrf
                        <button class="btn-ghost w-full !py-2">I've pressed Start — check now</button>
                    </form>
                </div>
            @endunless
        @endif

        <div class="space-y-3">
            @forelse ($alerts as $alert)
                @php $asset = $alert->asset; @endphp
                <div class="card flex items-center gap-3">
                    <a href="{{ route('alerts.edit', $alert) }}" class="flex min-w-0 flex-1 items-center gap-3">
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
                                {{ $alert->condition === 'above' ? 'Above' : 'Below' }} {{ $num($alert->target_price, $asset->currency) }}
                                @if ($asset->current_price !== null)
                                    · now {{ $num($asset->current_price, $asset->currency) }}
                                @endif
                            </div>
                            @if ($alert->triggered_at)
                                <div class="mt-0.5 text-xs text-muted">Triggered {{ $alert->triggered_at->diffForHumans() }}</div>
                            @endif
                        </div>
                    </a>
                    <div class="flex shrink-0 flex-col items-end gap-1.5">
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $alert->active ? 'bg-gain/15 text-gain' : 'bg-ink-600 text-muted' }}">
                            {{ $alert->active ? 'Active' : 'Triggered' }}
                        </span>
                        <div class="flex items-center gap-2 text-xs">
                            @unless ($alert->active)
                                <form method="POST" action="{{ route('alerts.rearm', $alert) }}">
                                    @csrf
                                    <button class="text-accent hover:underline">Re-arm</button>
                                </form>
                                <span class="text-muted/40">·</span>
                            @endunless
                            <x-confirm-form
                                :action="route('alerts.destroy', $alert)"
                                title="Delete this alert?"
                                :message="'The alert on '.$asset->name.' will be removed.'"
                                confirm="Delete"
                                trigger="Delete"
                                trigger-class="text-muted hover:text-loss" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="card text-center">
                    <p class="text-sm text-muted">No alerts yet.</p>
                    <p class="mt-1 text-xs text-muted">
                        Create one to get notified when a stock, ETF, index fund, commodity or crypto hits a price you care about.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.mobile>
