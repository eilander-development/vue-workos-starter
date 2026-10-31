<?php

namespace App\Models;

class Forecast extends CatalogModel
{
    protected $guarded = [];

    protected $casts = [
        'rules' => 'array',
        'snapshot' => 'array',
        'applied_at' => 'datetime',
        'reverted_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
