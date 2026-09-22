<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
{
    use HasFactory;

    public const CONDITIONS = ['above', 'below'];

    protected $fillable = [
        'user_id',
        'asset_id',
        'condition',
        'target_price',
        'active',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'target_price' => 'float',
            'active' => 'boolean',
            'triggered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** Whether the given live price satisfies this alert's condition. */
    public function isMet(float $price): bool
    {
        return $this->condition === 'above'
            ? $price >= $this->target_price
            : $price <= $this->target_price;
    }

    public function conditionLabel(): string
    {
        return $this->condition === 'above' ? 'rises above' : 'falls below';
    }
}
