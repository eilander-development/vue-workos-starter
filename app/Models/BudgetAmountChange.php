<?php

namespace App\Models;

class BudgetAmountChange extends CatalogModel
{
    protected $guarded = [];

    protected $casts = [
        'before_amounts' => 'array',
        'amounts' => 'array',
        'scheduled_months' => 'array',
        'known_values' => 'array',
    ];
}
