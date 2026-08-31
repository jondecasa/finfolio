@php
    /** @var \App\Models\Plan|null $plan */
    $plan = $plan ?? null;
    $isEdit = (bool) $plan;

    $currentMovement = $plan
        ? match (true) {
            $plan->target === 'quantity' => $plan->direction === 'in' ? 'buy' : 'sell',
            $plan->target === 'debt' => $plan->direction === 'in' ? 'debt_in' : 'debt_out',
            default => $plan->direction === 'in' ? 'value_in' : 'value_out',
        }
        : 'buy';

    $freqLabels = ['weekly' => 'Weekly', 'monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'yearly' => 'Yearly'];
@endphp

@if ($holdings->isEmpty())
    <div class="card text-center">
        <p class="text-sm text-muted">You need at least one position before you can schedule movements.</p>
        <a href="{{ route('holdings.create') }}" class="btn-primary mt-4 inline-flex">Add a position</a>
    </div>
@else
    <form method="POST"
          action="{{ $isEdit ? route('plans.update', $plan) : route('plans.store') }}"
          class="space-y-5"
          x-data="{
              meta: @js($holdingsMeta),
              holdingId: '{{ old('holding_id', $plan->holding_id ?? ($holdingsMeta[0]['id'] ?? '')) }}',
              movement: '{{ old('_movement', $currentMovement) }}',
              amountKind: '{{ old('amount_kind', $plan->amount_kind ?? 'units') }}',
              amount: '{{ old('amount', $plan->amount ?? '') }}',
              currency: '{{ old('currency', $plan->currency ?? $baseCurrency) }}',
              init() { this.reconcile(); },
              get selected() { return this.meta.find(m => String(m.id) === String(this.holdingId)) || null; },
              get isPriced() { return this.selected ? this.selected.priced : true; },
              get isRealEstate() { return this.selected ? this.selected.realestate : false; },
              get movements() {
                  if (this.isPriced) return [
                      { k: 'buy',  label: 'Buy',  t: 'quantity', d: 'in'  },
                      { k: 'sell', label: 'Sell', t: 'quantity', d: 'out' },
                  ];
                  if (this.isRealEstate) return [
                      { k: 'debt_out',  label: 'Reduce debt',    t: 'debt',  d: 'out' },
                      { k: 'debt_in',   label: 'Increase debt',  t: 'debt',  d: 'in'  },
                      { k: 'value_in',  label: 'Increase value', t: 'value', d: 'in'  },
                      { k: 'value_out', label: 'Decrease value', t: 'value', d: 'out' },
                  ];
                  return [
                      { k: 'value_in',  label: 'Add money',    t: 'value', d: 'in'  },
                      { k: 'value_out', label: 'Remove money', t: 'value', d: 'out' },
                  ];
              },
              get current() { return this.movements.find(m => m.k === this.movement) || this.movements[0]; },
              get outTarget() { return this.current.t; },
              get outDirection() { return this.current.d; },
              get outAmountKind() { return this.isPriced ? this.amountKind : 'cash'; },
              get needsCurrency() { return this.outAmountKind === 'cash'; },
              reconcile() {
                  if (!this.movements.find(m => m.k === this.movement)) this.movement = this.movements[0].k;
                  if (!this.isPriced) this.amountKind = 'cash';
                  if (this.selected && this.needsCurrency && !this.currency) this.currency = this.selected.cost_currency;
              },
          }"
          @change="reconcile()"
    >
        @csrf
        @if ($isEdit) @method('PUT') @endif
        <input type="hidden" name="_movement" :value="movement">
        <input type="hidden" name="target" :value="outTarget">
        <input type="hidden" name="direction" :value="outDirection">
        <input type="hidden" name="amount_kind" :value="outAmountKind">

        {{-- Position --}}
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Position</label>
            <select name="holding_id" class="field" x-model="holdingId" required>
                @foreach ($holdings->groupBy(fn ($h) => $h->account->name) as $accountName => $group)
                    <optgroup label="{{ $accountName }}">
                        @foreach ($group as $h)
                            <option value="{{ $h->id }}" @selected((string) old('holding_id', $plan->holding_id ?? '') === (string) $h->id)>
                                {{ $h->asset->symbol }} — {{ $h->asset->name }}
                                @if ($h->asset->type === 'realestate')
                                    ({{ \App\Support\Money::format((float) $h->debt, $h->asset->currency ?? 'EUR') }} debt)
                                @else
                                    ({{ rtrim(rtrim(number_format((float) $h->quantity, 8, '.', ''), '0'), '.') }} units)
                                @endif
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        {{-- Movement type --}}
        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Movement</label>
            <div class="flex flex-wrap gap-2">
                <template x-for="m in movements" :key="m.k">
                    <button type="button" class="tab" :class="movement === m.k ? 'tab-active' : ''"
                            @click="movement = m.k" x-text="m.label"></button>
                </template>
            </div>
            <p class="mt-1 text-xs text-muted">
                <span x-show="outDirection === 'in'">Adds to the position each time it runs.</span>
                <span x-show="outDirection === 'out'" x-cloak>Subtracts from the position. It will never go below zero.</span>
            </p>
        </div>

        {{-- Amount --}}
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label class="block text-sm font-semibold text-muted"
                       x-text="outAmountKind === 'units' ? 'Units per run' : 'Amount per run'"></label>
                <div class="flex gap-1" x-show="isPriced">
                    <button type="button" class="pill" :class="amountKind === 'units' ? 'pill-active' : ''" @click="amountKind = 'units'">Units</button>
                    <button type="button" class="pill" :class="amountKind === 'cash' ? 'pill-active' : ''" @click="amountKind = 'cash'; if (!currency && selected) currency = selected.cost_currency">Money</button>
                </div>
            </div>
            <div class="flex gap-3">
                <input type="number" step="any" min="0" inputmode="decimal"
                       class="field flex-1" name="amount" x-model="amount" required>
                <select name="currency" class="field w-28" x-model="currency" x-show="needsCurrency" x-cloak>
                    @foreach ($currencyOptions as $c)
                        <option value="{{ $c }}">{{ $c }}</option>
                    @endforeach
                </select>
            </div>
            <p class="mt-1 text-xs text-muted" x-show="outAmountKind === 'cash'" x-cloak>
                The price on the day it runs decides how many units that buys.
            </p>
        </div>

        {{-- Schedule --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">Frequency</label>
                <select name="frequency" class="field">
                    @foreach ($frequencies as $f)
                        <option value="{{ $f }}" @selected(old('frequency', $plan->frequency ?? 'monthly') === $f)>{{ $freqLabels[$f] ?? ucfirst($f) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-muted">First run</label>
                <input type="date" name="starts_on" class="field"
                       value="{{ old('starts_on', optional($plan->starts_on ?? null)->toDateString() ?? $today) }}" required>
            </div>
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Ends on <span class="text-muted/60">(optional)</span></label>
            <input type="date" name="ends_on" class="field"
                   value="{{ old('ends_on', optional($plan->ends_on ?? null)->toDateString()) }}">
        </div>

        <div>
            <label class="mb-1.5 block text-sm font-semibold text-muted">Note <span class="text-muted/60">(optional)</span></label>
            <input type="text" name="note" class="field" maxlength="255" value="{{ old('note', $plan->note ?? '') }}">
        </div>

        @if ($isEdit)
            <label class="flex items-center gap-2 text-sm text-muted">
                <input type="checkbox" name="active" value="1" class="h-4 w-4 rounded border-white/20 bg-ink-700"
                       @checked(old('active', $plan->active)) >
                Active
            </label>
        @endif

        <button class="btn-primary w-full">{{ $isEdit ? 'Save plan' : 'Create plan' }}</button>
    </form>

    @if ($isEdit)
        <div class="mt-3">
            <x-confirm-form
                :action="route('plans.destroy', $plan)"
                title="Delete this plan?"
                message="Movements already applied stay on your positions. Future runs stop."
                confirm="Delete plan"
                trigger="Delete plan"
                trigger-class="btn-ghost w-full text-loss" />
        </div>
    @endif
@endif
