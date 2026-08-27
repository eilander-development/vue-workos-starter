<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaidConnection extends Model
{
    use HasFactory;

    protected $connection = 'sqlite';

    protected $fillable = [
        'user_id',
        'item_id',
        'institution_name',
        'access_token',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
