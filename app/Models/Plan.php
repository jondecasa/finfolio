<?php

namespace App\Models;

use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A scheduled contribution / movement against an existing holding: "buy 1 BTC
 * every month", "pay 100 € off the mortgage every month", etc. The `plans:run`
 * command applies the ones that are due.
 */
class Plan extends Model
{
    use HasFactory;

    /** What the movement changes on the holding. */
    public const TARGETS = ['quantity', 'debt', 'value'];

    /** `in` adds (buy / draw down debt / raise value); `out` subtracts. */
    public const DIRECTIONS = ['in', 'out'];

    /**
     * How `amount` is expressed. `units` is only valid for the `quantity` target;
     * `percent` (a share of the current value) is only valid for the `value` target.
     */
    public const KINDS = ['units', 'cash', 'percent'];

    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'half_yearly', 'yearly'];

    protected $fillable = [
        'holding_id',
        'target',
        'direction',
        'amount_kind',
        'amount',
        'currency',
        'frequency',
        'starts_on',
        'ends_on',
        'next_run_on',
        'last_run_on',
        'active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'next_run_on' => 'date',
            'last_run_on' => 'date',
            'active' => 'boolean',
        ];
    }

    public function holding(): BelongsTo
    {
        return $this->belongsTo(Holding::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PlanRun::class)->latest('ran_on')->latest('id');
    }

    /** Plans whose next occurrence is on or before $on (and not past their end date). */
    public function scopeDue(Builder $query, CarbonInterface $on): Builder
    {
        return $query
            ->where('active', true)
            ->whereDate('next_run_on', '<=', $on)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereColumn('ends_on', '>=', 'next_run_on'));
    }

    /** The next occurrence strictly after $from. */
    public function advance(CarbonImmutable $from): CarbonImmutable
    {
        return match ($this->frequency) {
            'weekly' => $from->addWeek(),
            'quarterly' => $from->addMonthsNoOverflow(3),
            'half_yearly' => $from->addMonthsNoOverflow(6),
            'yearly' => $from->addYearNoOverflow(),
            default => $from->addMonthNoOverflow(),
        };
    }

    /** Advance repeatedly until we land after $on — missed periods are not replayed. */
    public function rollForwardAfter(CarbonImmutable $on): CarbonImmutable
    {
        $next = CarbonImmutable::parse($this->next_run_on->toDateString());

        // Cap the loop defensively; a decade of weekly steps is still < 600.
        for ($i = 0; $i < 1000 && ! $next->gt($on); $i++) {
            $next = $this->advance($next);
        }

        return $next;
    }

    public function isPriced(): bool
    {
        return $this->target === 'quantity';
    }

    public static function frequencyLabel(string $frequency): string
    {
        return [
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'half_yearly' => 'Half-yearly',
            'yearly' => 'Yearly',
        ][$frequency] ?? ucfirst($frequency);
    }

    /** Human summary used in lists and headings. */
    public function label(): string
    {
        $symbol = $this->holding?->asset?->symbol ?? 'position';
        $freq = self::frequencyLabel($this->frequency);

        $verb = match (true) {
            $this->target === 'quantity' => $this->direction === 'in' ? 'Buy' : 'Sell',
            $this->target === 'debt' => $this->direction === 'in' ? 'Increase debt on' : 'Reduce debt on',
            default => $this->direction === 'in' ? 'Add value to' : 'Reduce value of',
        };

        $qty = match ($this->amount_kind) {
            'units' => rtrim(rtrim(number_format($this->amount, 8, '.', ''), '0'), '.').' units',
            'percent' => rtrim(rtrim(number_format($this->amount, 4, '.', ''), '0'), '.').'%',
            default => Money::format($this->amount, $this->currency ?? 'EUR'),
        };

        return "{$verb} {$symbol} · {$qty} · {$freq}";
    }
}
