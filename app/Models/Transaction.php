<?php

namespace App\Models;

use App\Support\BankTransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $connection = 'ledger';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'date' => 'date',
        'is_pending' => 'boolean',
        'allocations' => 'array',
        'bank_payload' => 'array',
        'archived_at' => 'datetime',
        'lifecycle_metadata' => 'array',
    ];

    protected static function booted(): void
    {
        // History must be explicitly requested, including in aggregates and rule processing.
        static::addGlobalScope('active', fn (Builder $query) => $query->whereNull('transactions.archived_at'));
    }

    public function scopeWithHistory(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active');
    }

    public function scopeOnlyHistory(Builder $query): Builder
    {
        return $query->withoutGlobalScope('active')->whereNotNull('transactions.archived_at');
    }

    public function scopeBooked(Builder $query): Builder
    {
        return $query->where('is_pending', false);
    }

    public function getIsPendingAttribute(mixed $value): bool
    {
        return (bool) $value || BankTransactionStatus::isPending($this->bank_payload ?? []);
    }

    public function revision(): string
    {
        $attributes = $this->getRawOriginal();
        ksort($attributes);

        return hash('sha256', json_encode($attributes, JSON_THROW_ON_ERROR));
    }

    public function scopeForPublicKey(Builder $query, string $key): Builder
    {
        return $query->where(function (Builder $match) use ($key) {
            $match->where('key', $key);
            if (preg_match('/^tx-([0-9]+)$/', $key, $parts)) {
                $match->orWhere(fn (Builder $legacy) => $legacy->whereNull('key')->where('id', $parts[1]));
            }
        });
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function importRule(): BelongsTo
    {
        return $this->belongsTo(ImportRule::class, 'rule_id');
    }
}
