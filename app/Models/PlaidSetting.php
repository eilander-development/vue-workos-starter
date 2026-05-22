<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaidSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'secret' => 'encrypted',
    ];

    public static function active(): ?self
    {
        return self::orderByDesc('updated_at')->first();
    }
}
