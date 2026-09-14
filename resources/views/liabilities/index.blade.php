@php
    $currency = $mortgages['currency'];
    $hidden = auth()->user()->values_hidden;
    $rows = $mortgages['rows'];
@endphp

<x-layouts.mobile heading="Liabilities" title="Finfolio · Liabilities">
    <div class="lg:mx-auto lg:max-w-3xl">
        @if ($rows->isEmpty())
            <div class="app-pad">
                <div class="card text-center">
                    <p class="font-semibold">No liabilities tracked yet</p>
                    <p class="mt-1 text-sm text-muted">
                        Add a mortgage to a <span class="text-white">Real estate</span> position and it'll show up
                        here, with a progress bar tracking how much of it you've paid off.
                    </p>
                </div>
            </div>
        @else
            {{-- Overview --}}
            <div class="app-pad grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="card-tight">
                    <div class="text-xs text-muted">Initial mortgage debt</div>
                    <x-money :amount="$mortgages['total_initial']" :currency="$currency" :hidden="$hidden" class="mt-1 block text-xl font-bold" />
                </div>
                <div class="card-tight">
                    <div class="text-xs text-muted">Current mortgage</div>
                    <x-money :amount="$mortgages['total_current']" :currency="$currency" :hidden="$hidden" class="mt-1 block text-xl font-bold" />
                </div>
                <div class="card-tight">
                    <div class="text-xs text-muted">Paid off</div>
                    <span class="value-up mt-1 block text-xl font-bold"><x-money :amount="$mortgages['total_paid_off']" :currency="$currency" :hidden="$hidden" /></span>
                </div>
                <div class="card-tight">
                    <div class="text-xs text-muted">Progress</div>
                    <span class="mt-1 block text-xl font-bold">{{ number_format($mortgages['progress_pct'], 1) }}%</span>
                </div>
            </div>

            <div class="app-pad mt-4">
                <div class="card">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-semibold">Overall</span>
                        <span class="text-muted">{{ number_format($mortgages['progress_pct'], 1) }}% paid off</span>
                    </div>
                    <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-ink-700">
                        <div class="h-full rounded-full bg-gain" style="width: {{ number_format($mortgages['progress_pct'], 2) }}%"></div>
                    </div>
                </div>
            </div>

            {{-- One card per mortgage --}}
            <div class="app-pad mt-4 space-y-3">
                @foreach ($rows as $row)
                    @php $h = $row['holding']; @endphp
                    <a href="{{ route('holdings.edit', $h) }}" class="card block transition hover:bg-ink-700">
                        <div class="flex items-center justify-between gap-2">
                            <span class="min-w-0 truncate font-semibold">{{ $row['name'] }}</span>
                            <span class="shrink-0 text-sm text-muted">{{ number_format($row['progress_pct'], 1) }}%</span>
                        </div>

                        <div class="mt-2 h-2.5 w-full overflow-hidden rounded-full bg-ink-700">
                            <div class="h-full rounded-full {{ $row['progress_pct'] >= 100 ? 'bg-gain' : 'bg-accent' }}"
                                 style="width: {{ number_format($row['progress_pct'], 2) }}%"></div>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-xs text-muted">
                            <span>
                                Paid <x-money :amount="$row['paid_off']" :currency="$currency" :hidden="$hidden" />
                                of <x-money :amount="$row['initial']" :currency="$currency" :hidden="$hidden" />
                            </span>
                            <span class="{{ $row['current'] > 0 ? 'value-down' : 'value-up' }} font-semibold">
                                @if ($row['current'] > 0)
                                    − <x-money :amount="$row['current']" :currency="$currency" :hidden="$hidden" /> left
                                @else
                                    Paid off
                                @endif
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.mobile>
