<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsGoal extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'initial_amount' => 'decimal:2',
        'monthly_contribution' => 'decimal:2',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_key', 'key');
    }
}
