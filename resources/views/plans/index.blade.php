@php
    $freqLabels = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'half_yearly' => 'Half-yearly', 'yearly' => 'Yearly'];
    $today = \Carbon\CarbonImmutable::today();
@endphp

<x-layouts.mobile heading="Plans" title="Finfolio · Plans" :back="route('wealth')">
    <div class="app-pad lg:mx-auto lg:max-w-2xl">

        <div class="mb-4 flex items-center gap-2">
            <a href="{{ route('plans.create') }}" class="btn-primary flex-1">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14"/></svg>
                New plan
            </a>
            @if ($plans->where('active', true)->isNotEmpty())
                <form method="POST" action="{{ route('plans.runDue') }}">
                    @csrf
                    <button class="btn-ghost">Run due now</button>
                </form>
            @endif
        </div>

        <p class="mb-4 text-xs text-muted">
            Automatic movements run once a day. A due plan buys or sells at that day's price and
            updates your position; missed days while the app was offline are not backfilled.
        </p>

        <div class="space-y-3">
            @forelse ($plans as $plan)
                @php
                    $status = ! $plan->active
                        ? ['Paused', 'bg-ink-600 text-muted']
                        : (($plan->ends_on && $plan->ends_on->lt($today))
                            ? ['Ended', 'bg-ink-600 text-muted']
                            : ['Active', 'bg-gain/15 text-gain']);
                @endphp
                <a href="{{ route('plans.show', $plan) }}" class="card flex items-center gap-3 transition hover:bg-ink-700">
                    <span class="logo-bubble">
                        @if ($plan->holding->asset->logo_url)
                            <img src="{{ $plan->holding->asset->logo_url }}" alt="" class="h-full w-full object-cover">
                        @else
                            {{ \Illuminate\Support\Str::substr($plan->holding->asset->symbol, 0, 3) }}
                        @endif
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-semibold">{{ $plan->label() }}</div>
                        <div class="text-xs text-muted">
                            {{ $plan->holding->account->name }}
                            @if ($plan->active)
                                · next {{ $plan->next_run_on?->isoFormat('D MMM YYYY') }}
                            @elseif ($plan->last_run_on)
                                · last ran {{ $plan->last_run_on->isoFormat('D MMM YYYY') }}
                            @endif
                            @if ($plan->runs_count) · {{ $plan->runs_count }} {{ \Illuminate\Support\Str::plural('run', $plan->runs_count) }} @endif
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span>
                </a>
            @empty
                <div class="card text-center">
                    <p class="text-sm text-muted">No automatic movements yet.</p>
                    <p class="mt-1 text-xs text-muted">
                        Create one to dollar-cost-average into a position, or to chip away at a mortgage every month.
                    </p>
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.mobile>
