<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends CatalogModel
{
    protected $guarded = [];

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
