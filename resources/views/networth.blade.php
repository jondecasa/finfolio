@php
    $currency = $overview['currency'];
    $hidden = auth()->user()->values_hidden;
@endphp

<x-layouts.mobile heading="Net Worth" title="Finfolio · Net Worth" wide>
    <div class="app-pad">
        <div class="flex gap-6 border-b border-white/5 pb-2 text-lg font-bold">
            <span class="text-white">Overview</span>
            <a href="{{ route('wealth') }}" class="text-muted">Investments</a>
        </div>

        <div class="mt-5 flex items-center justify-between">
            <span class="text-sm font-medium text-muted">Total Net Worth</span>
        </div>
        <div class="mt-1 flex items-end justify-between">
            <x-money :amount="$overview['total_value']" :currency="$currency" :hidden="$hidden" class="stat-value" />
            <x-change :value="$overview['day_change']" :pct="$overview['day_change_pct']" :currency="$currency" :hidden="$hidden" class="pb-1 text-sm" />
        </div>
    </div>

    <div class="mt-3">
        <x-net-worth-chart :series="$series" :ranges="$ranges" :active-range="$activeRange" :height="180" :lg-height="320" />
    </div>

    {{-- Investments --}}
    <div class="app-pad mt-8">
        <h2 class="mb-3 text-2xl font-bold">Investments</h2>
        <div class="card">
            <a href="{{ route('wealth') }}" class="row-link !py-0">
                <div>
                    <div class="font-bold">Aggregated</div>
                    <div class="text-xs text-muted">Sum of all accounts</div>
                </div>
                <div class="flex items-center gap-2 text-right">
                    <div>
                        <x-money :amount="$overview['total_value']" :currency="$currency" :hidden="$hidden" class="block font-semibold" />
                        <x-change :pct="$overview['day_change_pct']" class="text-xs" />
                    </div>
                    <span class="text-muted">&rsaquo;</span>
                </div>
            </a>

            <div class="mt-2 space-y-1 border-l border-white/10 pl-3">
                @foreach ($overview['accounts'] as $row)
                    <a href="{{ route('wealth', ['account' => $row['account']->id]) }}" class="row-link">
                        <div class="flex items-center gap-3">
                            <span class="logo-bubble bg-ink-600">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                            </span>
                            <div class="font-semibold">{{ $row['account']->name }}</div>
                        </div>
                        <div class="flex items-center gap-2 text-right">
                            <div>
                                <x-money :amount="$row['value']" :currency="$currency" :hidden="$hidden" class="block font-semibold" />
                                <x-change :pct="$row['day_change_pct']" class="text-xs" />
                            </div>
                            <span class="text-muted">&rsaquo;</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="lg:grid lg:grid-cols-2 lg:gap-6">
        {{-- Cash balance --}}
        <div class="app-pad mt-6">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-2xl font-bold">Cash balance</h2>
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
        <div class="app-pad mt-6">
            <h2 class="mb-3 text-2xl font-bold">Liabilities</h2>
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
