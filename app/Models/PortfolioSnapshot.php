<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'account_id',
        'value',
        'invested',
        'currency',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'invested' => 'float',
            'captured_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeAggregate($query)
    {
        return $query->whereNull('account_id');
    }
}
