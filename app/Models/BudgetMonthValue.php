<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetMonthValue extends Model
{
    protected $guarded = [];

    protected $casts = [
        'estimated' => 'decimal:2',
        'year' => 'integer',
        'entries' => 'array',
    ];

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
