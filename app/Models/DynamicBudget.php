<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DynamicBudget extends Model
{
    protected $connection = 'sqlite';
    protected $guarded = [];
}

