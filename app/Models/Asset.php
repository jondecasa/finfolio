<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    public const TYPES = ['crypto', 'stock', 'etf', 'index', 'fund', 'commodity', 'realestate', 'cash', 'other'];

    /** Types whose price is fetched from a provider (everything else is manual). */
    public const PRICED_TYPES = ['crypto', 'stock', 'etf', 'index', 'fund', 'commodity'];

    /** Manually-valued types: no search, no live price. */
    public const MANUAL_TYPES = ['realestate', 'other', 'cash'];

    public function typeLabel(): string
    {
        return config("finfolio.categories.{$this->type}.label")
            ?? ucfirst($this->type);
    }

    protected $fillable = [
        'type',
        'symbol',
        'provider_id',
        'name',
        'exchange',
        'currency',
        'logo_url',
        'current_price',
        'previous_close',
        'change_pct',
        'meta',
        'price_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'current_price' => 'float',
            'previous_close' => 'float',
            'change_pct' => 'float',
            'price_updated_at' => 'datetime',
        ];
    }

    public function holdings(): HasMany
    {
        return $this->hasMany(Holding::class);
    }

    public function isPriceStale(int $seconds = 900): bool
    {
        return ! $this->price_updated_at || $this->price_updated_at->lt(now()->subSeconds($seconds));
    }

    public function dayChangePct(): ?float
    {
        if ($this->change_pct !== null) {
            return $this->change_pct;
        }

        if ($this->current_price && $this->previous_close) {
            return ($this->current_price - $this->previous_close) / $this->previous_close * 100;
        }

        return null;
    }

    /**
     * Where this asset's live quote comes from, so the UI can link out to it.
     *
     * @return array{name: string, url: string}|null null for manual ("other") assets
     */
    public function priceSource(): ?array
    {
        return match (true) {
            $this->type === 'crypto' => [
                'name' => 'CoinGecko',
                'url' => $this->provider_id
                    ? 'https://www.coingecko.com/en/coins/'.$this->provider_id
                    : 'https://www.coingecko.com/en/search?query='.rawurlencode($this->symbol),
            ],
            in_array($this->type, ['stock', 'etf', 'index', 'fund', 'commodity'], true) => [
                'name' => 'Yahoo Finance',
                'url' => 'https://finance.yahoo.com/quote/'.rawurlencode($this->symbol),
            ],
            default => null,
        };
    }
}
