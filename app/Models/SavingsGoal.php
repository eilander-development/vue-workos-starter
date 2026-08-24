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
        'budget_keys' => 'array',
    ];

    /** @return list<string> */
    public function budgetKeys(): array
    {
        $fromJson = collect($this->budget_keys ?? [])
            ->filter(fn ($key) => is_string($key) && $key !== '')
            ->values()
            ->all();

        if ($fromJson !== []) {
            return array_values(array_unique($fromJson));
        }

        return filled($this->budget_key) ? [(string) $this->budget_key] : [];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class, 'budget_key', 'key');
    }
}
