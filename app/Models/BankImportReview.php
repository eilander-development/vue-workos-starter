<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankImportReview extends Model
{
    protected $connection = 'ledger';

    protected $guarded = [];

    protected $casts = ['incoming_payload' => 'array', 'existing_keys' => 'array', 'resolved_at' => 'datetime', 'seen_at' => 'datetime'];

    public function revision(): string
    {
        return hash('sha256', json_encode([$this->key, $this->incoming_payload, $this->existing_keys, $this->status, $this->resolved_key, $this->resolved_at?->toIso8601String()], JSON_THROW_ON_ERROR));
    }
}
