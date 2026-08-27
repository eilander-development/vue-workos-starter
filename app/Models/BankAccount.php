<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $connection = 'sqlite';
    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'last_synced_at' => 'datetime',
        'sync_count_date' => 'date',
    ];

    /** Meest recent gesynchroniseerde betaalrekening (voor huidig saldo). */
    public static function latestChecking(): ?self
    {
        return static::query()
            ->where('type', 'checking')
            ->orderByDesc('last_synced_at')
            ->orderByDesc('id')
            ->first();
    }
}
