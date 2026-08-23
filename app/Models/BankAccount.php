<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $guarded = [];

    protected $casts = [
        'balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'last_synced_at' => 'datetime',
        'sync_count_date' => 'date',
    ];
}
