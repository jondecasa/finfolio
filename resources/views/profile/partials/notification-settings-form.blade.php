@php
    $telegramLinked = $user->hasTelegramLinked();
@endphp

<div>
    <h2 class="text-lg font-bold">Notifications</h2>
    <p class="mt-1 text-sm text-muted">
        Where <a href="{{ route('alerts.index') }}" class="underline">price alerts</a> reach you. Both are optional
        and independent — connect either, both, or neither.
    </p>
</div>

{{-- Telegram --}}
<div class="mt-5 border-t border-white/5 pt-4">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="font-semibold">Telegram</div>
            <div class="text-sm text-muted">
                @if (! $telegramConfigured)
                    Not set up on this server.
                @elseif ($telegramLinked)
                    Connected.
                @else
                    Not connected.
                @endif
            </div>
        </div>
        @if ($telegramConfigured)
            @if ($telegramLinked)
                <form method="POST" action="{{ route('notifications.telegram.disconnect') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn-ghost shrink-0">Disconnect</button>
                </form>
            @else
                <form method="POST" action="{{ route('notifications.telegram.connect') }}">
                    @csrf
                    <button class="btn-primary shrink-0">Connect</button>
                </form>
            @endif
        @endif
    </div>

    @if ($telegramConfigured && ! $telegramLinked && $telegramLinkUrl)
        <div class="mt-3 rounded-2xl bg-ink-700 p-3 text-sm">
            <p class="text-muted">Open this link and press <strong class="text-white">Start</strong> in Telegram, then come back and check:</p>
            <a href="{{ $telegramLinkUrl }}" target="_blank" rel="noopener noreferrer"
               class="mt-2 inline-flex items-center gap-1 text-accent underline">
                Open Telegram
                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M8 7h9v9"/></svg>
            </a>
            <form method="POST" action="{{ route('notifications.telegram.check') }}" class="mt-3">
                @csrf
                <button class="btn-ghost w-full !py-2">I've pressed Start — check now</button>
            </form>
        </div>
    @endif
</div>

{{-- Browser push --}}
<div class="mt-5 border-t border-white/5 pt-4" x-data="pushSettings(@js($vapidPublicKey))">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="font-semibold">Browser push</div>
            <div class="text-sm text-muted" x-text="statusText"></div>
        </div>
        <button type="button" class="btn-primary shrink-0" x-show="status === 'available'" @click="subscribe()">Enable</button>
        <button type="button" class="btn-ghost shrink-0" x-show="status === 'subscribed'" @click="unsubscribe()">Disable</button>
    </div>

    @if ($webPushConfigured)
        <form method="POST" action="{{ route('notifications.push.test') }}" class="mt-3" x-show="status === 'subscribed'" x-cloak>
            @csrf
            <button class="btn-ghost w-full !py-2">Send test notification</button>
        </form>
    @endif
</div>
