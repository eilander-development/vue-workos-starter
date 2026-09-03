<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckingPeriodSnapshot extends Model
{
    protected $connection = 'sqlite';

    protected $guarded = [];

    protected $casts = [
        'year' => 'integer',
        'balance' => 'decimal:2',
        'period_end' => 'date',
        'captured_at' => 'datetime',
    ];
}
