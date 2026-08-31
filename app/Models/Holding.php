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
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'average_cost' => 'float',
            'manual_value' => 'float',
            'debt' => 'float',
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
     * Gross market value in the asset's native currency — the current worth of
     * the position, before any attached debt. Uses the per-holding manual value
     * when set (manual assets), otherwise quantity × the asset's live price.
     */
    public function grossValue(): float
    {
        if ($this->manual_value !== null) {
            return (float) $this->manual_value;
        }

        return (float) $this->quantity * (float) ($this->asset->current_price ?? 0);
    }

    /** Alias kept for readability where "market value" reads better. */
    public function marketValue(): float
    {
        return $this->grossValue();
    }

    /** Outstanding debt attached to this holding (e.g. a mortgage). */
    public function debtAmount(): float
    {
        return (float) $this->debt;
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

    /** Total invested (cost basis), in costCurrency(). */
    public function costBasis(): float
    {
        return (float) $this->quantity * (float) ($this->average_cost ?? 0);
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
}
