@php
    $currency = $overview['currency'];
    $hidden = auth()->user()->values_hidden;
@endphp

<x-layouts.mobile heading="Home" title="Finfolio · Home" wide>
    <div
        class="app-pad"
        x-data="{
            change: {{ $series['change'] ?? 0 }},
            changePct: {{ $series['change_pct'] ?? 0 }},
            range: @js($activeRange),
        }"
        @series-updated.window="change = $event.detail.change; changePct = $event.detail.change_pct; range = $event.detail.range"
    >
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-muted">Total Net Worth</span>
            <div class="flex items-center gap-4 text-sm text-muted">
                <form method="POST" action="{{ route('portfolio.visibility') }}">
                    @csrf
                    <button class="flex items-center gap-1.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                        {{ $hidden ? 'Show' : 'Hide' }}
                    </button>
                </form>
            </div>
        </div>

        <div class="mt-1 flex items-end justify-between">
            <x-money :amount="$overview['total_value']" :currency="$currency" :hidden="$hidden" class="stat-value" />
            <div class="pb-1 text-right text-sm">
                @if ($hidden)
                    <div class="font-semibold text-muted">••••</div>
                @else
                    <div class="inline-flex items-center gap-1 font-semibold"
                         :class="changePct >= 0 ? 'value-up' : 'value-down'">
                        <svg class="h-3.5 w-3.5" :class="changePct >= 0 ? '' : 'rotate-180'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17 17 7M9 7h8v8"/></svg>
                        <span x-text="(changePct >= 0 ? '' : '−') + Math.abs(changePct).toFixed(2) + '%'"></span>
                    </div>
                    <div :class="changePct >= 0 ? 'value-up' : 'value-down'"
                         x-text="window.Finfolio.formatCurrency(Math.abs(change), @js($currency))"></div>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-4">
        <x-net-worth-chart :series="$series" :ranges="$ranges" :active-range="$activeRange" :height="230" :lg-height="360" />
    </div>

    <div class="mt-8 lg:grid lg:grid-cols-2 lg:gap-6 lg:items-start">
    {{-- Allocation preview --}}
    <div class="app-pad lg:mt-0">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-bold">Allocation</h2>
            <a href="{{ route('analytics') }}" class="text-sm text-muted hover:text-white">See all</a>
        </div>
        <div class="card space-y-4">
            @forelse ($allocation['positions']->take(5) as $p)
                <div class="row-link !py-0">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="logo-bubble">
                            @if ($p['logo_url'])
                                <img src="{{ $p['logo_url'] }}" alt="" class="h-full w-full object-cover">
                            @else
                                {{ \Illuminate\Support\Str::substr($p['symbol'], 0, 3) }}
                            @endif
                        </span>
                        <div class="min-w-0">
                            <div class="truncate font-semibold">{{ $p['name'] }}</div>
                            <div class="text-xs text-muted">{{ number_format($p['weight'], 1) }}%</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-money :amount="$p['value']" :currency="$currency" :hidden="$hidden" class="block font-semibold" />
                        @if ($p['day_change_pct'] !== null)
                            <x-change :pct="$p['day_change_pct']" class="text-xs" />
                        @endif
                    </div>
                </div>
                @if (! $loop->last)<div class="h-px bg-white/5"></div>@endif
            @empty
                <p class="py-6 text-center text-sm text-muted">
                    No positions yet. Tap <span class="font-semibold text-white">+</span> to add your first one.
                </p>
            @endforelse
        </div>
    </div>

    {{-- Accounts --}}
    <div class="app-pad mt-6 lg:mt-0">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-lg font-bold">Accounts</h2>
            <a href="{{ route('accounts.index') }}" class="text-sm text-muted hover:text-white">Manage</a>
        </div>
        <div class="card divide-y divide-white/5">
            @foreach ($overview['accounts'] as $row)
                <a href="{{ route('positions', ['account' => $row['account']->id]) }}" class="row-link">
                    <div class="flex items-center gap-3">
                        <span class="logo-bubble bg-ink-600">{{ \Illuminate\Support\Str::substr($row['account']->name, 0, 1) }}</span>
                        <div>
                            <div class="font-semibold">{{ $row['account']->name }}</div>
                            <div class="text-xs text-muted">{{ $row['positions'] }} {{ \Illuminate\Support\Str::plural('position', $row['positions']) }}</div>
                        </div>
                    </div>
                    <div class="text-right">
                        <x-money :amount="$row['value']" :currency="$currency" :hidden="$hidden" class="block font-semibold" />
                        @if ($row['day_change_pct'] !== null)
                            <x-change :pct="$row['day_change_pct']" class="text-xs" />
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    </div>

    <div class="mt-6 lg:grid lg:grid-cols-2 lg:gap-6 lg:items-start">
        {{-- Cash balance --}}
        <div class="app-pad lg:mt-0">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold">Cash balance</h2>
                <a href="{{ route('holdings.create', ['type' => 'cash']) }}" class="text-2xl leading-none text-muted hover:text-white">+</a>
            </div>
            <div class="card">
                @if ($overview['cash_total'] > 0)
                    <div class="flex items-center justify-between">
                        <span class="text-muted">Total cash</span>
                        <x-money :amount="$overview['cash_total']" :currency="$currency" :hidden="$hidden" class="text-xl font-bold" />
                    </div>
                @else
                    <p class="text-center font-semibold">No cash added</p>
                    <p class="mt-1 text-center text-sm text-muted">Add a <span class="text-white">Cash</span> position to include pure cash in your net worth.</p>
                @endif
            </div>
        </div>

        {{-- Liabilities --}}
        <div class="app-pad mt-6 lg:mt-0">
            <h2 class="mb-3 text-lg font-bold">Liabilities</h2>
            <div class="card">
                @if ($overview['total_debt'] > 0)
                    <div class="flex items-center justify-between">
                        <span class="text-muted">Total debt</span>
                        <span class="value-down text-xl font-bold">
                            − <x-money :amount="$overview['total_debt']" :currency="$currency" :hidden="$hidden" />
                        </span>
                    </div>
                    <div class="mt-3 divide-y divide-white/5">
                        @foreach ($overview['debt_holdings'] as $row)
                            <a href="{{ route('holdings.edit', $row['holding']) }}" class="row-link text-sm">
                                <span class="truncate">{{ $row['holding']->asset->name }}</span>
                                <span class="value-down font-semibold">− <x-money :amount="$row['debt']" :currency="$currency" :hidden="$hidden" /></span>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-center font-semibold">No liabilities</p>
                    <p class="mt-1 text-center text-sm text-muted">A mortgage on a <span class="text-white">Real estate</span> position shows up here.</p>
                @endif
            </div>
        </div>
    </div>
</x-layouts.mobile>
