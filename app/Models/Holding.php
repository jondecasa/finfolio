<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Holding extends Model
{
    use HasFactory;

    /** Asset types the user may recategorise a holding between (all Yahoo-priced). */
    public const RECATEGORISABLE = ['stock', 'etf', 'index', 'fund', 'commodity'];

    protected $fillable = [
        'account_id',
        'asset_id',
        'category',
        'quantity',
        'average_cost',
        'cost_currency',
        'manual_value',
        'debt',
        'mortgage_down_payment',
        'ownership_pct',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'average_cost' => 'float',
            'manual_value' => 'float',
            'debt' => 'float',
            'mortgage_down_payment' => 'float',
            'ownership_pct' => 'float',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /** Type used for display and allocation grouping (the per-holding override, else the asset's type). */
    public function displayType(): string
    {
        return $this->category ?: ($this->asset->type ?? 'other');
    }

    public function typeLabel(): string
    {
        $type = $this->displayType();

        return config("finfolio.categories.$type.label") ?? Str::headline($type);
    }

    public function canRecategorise(): bool
    {
        return in_array($this->asset->type, self::RECATEGORISABLE, true);
    }

    /**
     * Share of the position actually owned by the user (real estate
     * co-ownership, e.g. 50% of a jointly-owned flat) — 1.0 unless set otherwise.
     */
    public function ownershipFraction(): float
    {
        return $this->ownership_pct !== null ? ((float) $this->ownership_pct / 100) : 1.0;
    }

    /**
     * Gross market value in the asset's native currency — the current worth of
     * the position, before any attached debt. Uses the per-holding manual value
     * when set (manual assets), otherwise quantity × the asset's live price.
     * Scaled down to the user's ownership share.
     */
    public function grossValue(): float
    {
        if ($this->manual_value !== null) {
            return (float) $this->manual_value * $this->ownershipFraction();
        }

        return (float) $this->quantity * (float) ($this->asset->current_price ?? 0) * $this->ownershipFraction();
    }

    /** Alias kept for readability where "market value" reads better. */
    public function marketValue(): float
    {
        return $this->grossValue();
    }

    /** Outstanding debt attached to this holding (e.g. a mortgage), scaled to the user's ownership share. */
    public function debtAmount(): float
    {
        return (float) $this->debt * $this->ownershipFraction();
    }

    /** Net value that counts towards net worth: gross value minus debt. */
    public function netValue(): float
    {
        return $this->grossValue() - $this->debtAmount();
    }

    /** Currency the purchase price / cost basis is expressed in. */
    public function costCurrency(): string
    {
        return $this->cost_currency ?: ($this->asset->currency ?? 'USD');
    }

    /** Total invested (cost basis), in costCurrency(), scaled to the user's ownership share. */
    public function costBasis(): float
    {
        return (float) $this->quantity * (float) ($this->average_cost ?? 0) * $this->ownershipFraction();
    }

    /** Appreciation is measured gross (purchase price → current value), debt aside. */
    public function unrealizedGain(): float
    {
        return $this->grossValue() - $this->costBasis();
    }

    public function unrealizedGainPct(): ?float
    {
        $cost = $this->costBasis();

        return $cost > 0 ? $this->unrealizedGain() / $cost * 100 : null;
    }

    /**
     * Cash equity actually put into the position, in costCurrency(). For real
     * estate this is just the mortgage down payment (the rest was financed and
     * is tracked separately via `debt`) — or the full purchase price when no
     * down payment is set, i.e. bought outright with cash. For everything else
     * it's the full cost basis. Scaled to the user's ownership share.
     */
    public function investedEquity(): float
    {
        if ($this->asset->type === 'realestate') {
            $full = $this->mortgage_down_payment !== null
                ? (float) $this->mortgage_down_payment
                : (float) ($this->average_cost ?? 0);

            return $full * $this->ownershipFraction();
        }

        return $this->costBasis();
    }

    /** Profit against the cash equity invested, not the full cost basis (net of debt). */
    public function equityGain(): float
    {
        return $this->netValue() - $this->investedEquity();
    }

    public function equityGainPct(): ?float
    {
        $equity = $this->investedEquity();

        return $equity > 0 ? $this->equityGain() / $equity * 100 : null;
    }
}
