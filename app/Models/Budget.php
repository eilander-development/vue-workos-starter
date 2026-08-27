<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends CatalogModel
{
    protected $guarded = [];

    protected $casts = [
        'budget' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function monthValues(): HasMany
    {
        return $this->hasMany(BudgetMonthValue::class);
    }
}
