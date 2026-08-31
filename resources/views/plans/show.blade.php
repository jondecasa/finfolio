@php
    $freqLabels = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];
    $asset = $plan->holding->asset;
    $num = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 8, '.', ''), '0'), '.');
@endphp

<x-layouts.mobile heading="Plan" title="Finfolio · Plan" :back="route('plans.index')">
    <div class="app-pad lg:mx-auto lg:max-w-2xl">

        <div class="card">
            <div class="flex items-start gap-3">
                <span class="logo-bubble">
                    @if ($asset->logo_url)
                        <img src="{{ $asset->logo_url }}" alt="" class="h-full w-full object-cover">
                    @else
                        {{ \Illuminate\Support\Str::substr($asset->symbol, 0, 3) }}
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold">{{ $plan->label() }}</div>
                    <a href="{{ route('holdings.edit', $plan->holding) }}" class="text-xs text-accent hover:underline">
                        {{ $asset->name }} · {{ $plan->holding->account->name }}
                    </a>
                </div>
                @if (! $plan->active)
                    <span class="shrink-0 rounded-full bg-ink-600 px-2.5 py-1 text-xs font-semibold text-muted">Paused</span>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-xs text-muted">Frequency</dt>
                    <dd class="font-semibold">{{ $freqLabels[$plan->frequency] ?? ucfirst($plan->frequency) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Amount per run</dt>
                    <dd class="font-semibold">
                        @if ($plan->amount_kind === 'units')
                            {{ $num($plan->amount) }} units
                        @else
                            {{ \App\Support\Money::format((float) $plan->amount, $plan->currency ?? 'EUR') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Next run</dt>
                    <dd class="font-semibold">{{ $plan->active ? $plan->next_run_on?->isoFormat('D MMM YYYY') : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Last run</dt>
                    <dd class="font-semibold">{{ $plan->last_run_on?->isoFormat('D MMM YYYY') ?? 'Never' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Starts</dt>
                    <dd class="font-semibold">{{ $plan->starts_on?->isoFormat('D MMM YYYY') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted">Ends</dt>
                    <dd class="font-semibold">{{ $plan->ends_on?->isoFormat('D MMM YYYY') ?? 'Open-ended' }}</dd>
                </div>
            </dl>

            @if ($plan->note)
                <p class="mt-3 text-sm text-muted">{{ $plan->note }}</p>
            @endif

            <div class="mt-5 flex gap-2">
                <form method="POST" action="{{ route('plans.run', $plan) }}" class="flex-1">
                    @csrf
                    <button class="btn-primary w-full">Run now</button>
                </form>
                <a href="{{ route('plans.edit', $plan) }}" class="btn-ghost flex-1 text-center">Edit</a>
            </div>
        </div>

        <h2 class="mb-3 mt-6 text-lg font-bold">History</h2>

        @if ($runs->isEmpty())
            <div class="card text-center text-sm text-muted">Nothing has run yet.</div>
        @else
            <div class="card divide-y divide-white/5 !p-0">
                @foreach ($runs as $run)
                    <div class="flex items-center gap-3 px-4 py-3 {{ $run->status === 'skipped' ? 'opacity-60' : '' }}">
                        <div class="w-20 shrink-0 text-xs text-muted">{{ $run->ran_on->isoFormat('D MMM YY') }}</div>
                        <div class="min-w-0 flex-1">
                            @if ($run->status === 'skipped')
                                <div class="text-sm font-semibold text-muted">Skipped</div>
                                <div class="truncate text-xs text-muted">{{ $run->note }}</div>
                            @elseif ($run->units_delta !== null)
                                <div class="text-sm font-semibold {{ $run->units_delta >= 0 ? 'value-up' : 'value-down' }}">
                                    {{ $run->units_delta >= 0 ? '+' : '−' }}{{ $num(abs($run->units_delta)) }} units
                                </div>
                                <div class="text-xs text-muted">
                                    at {{ \App\Support\Money::format((float) $run->unit_price, $run->asset_currency ?? 'USD') }}
                                    · now {{ $num($run->resulting_quantity) }}
                                </div>
                            @else
                                <div class="text-sm font-semibold">
                                    {{ \App\Support\Money::format((float) $run->cash_amount, $run->cash_currency ?? 'EUR') }}
                                    @if ($run->resulting_debt !== null) to debt @else to value @endif
                                </div>
                                <div class="text-xs text-muted">
                                    @if ($run->resulting_debt !== null)
                                        debt now {{ \App\Support\Money::format((float) $run->resulting_debt, $run->asset_currency ?? 'EUR') }}
                                    @else
                                        value now {{ \App\Support\Money::format((float) $run->resulting_value, $run->asset_currency ?? 'EUR') }}
                                    @endif
                                    @if ($run->note) · {{ $run->note }} @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.mobile>
