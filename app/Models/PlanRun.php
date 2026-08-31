<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution of a {@see Plan}: what moved and where the position landed.
 */
class PlanRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'ran_on',
        'status',
        'units_delta',
        'cash_amount',
        'cash_currency',
        'unit_price',
        'asset_currency',
        'resulting_quantity',
        'resulting_avg_cost',
        'resulting_debt',
        'resulting_value',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'ran_on' => 'date',
            'units_delta' => 'float',
            'cash_amount' => 'float',
            'unit_price' => 'float',
            'resulting_quantity' => 'float',
            'resulting_avg_cost' => 'float',
            'resulting_debt' => 'float',
            'resulting_value' => 'float',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function wasApplied(): bool
    {
        return $this->status === 'applied';
    }
}
