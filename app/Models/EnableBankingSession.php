<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EnableBankingSession extends Model
{
    protected $connection = 'sqlite';
    protected $guarded = [];

    protected $casts = [
        'valid_until' => 'datetime',
        'accounts' => 'array',
    ];

    public static function validUntilFromPayload(array $payload): ?Carbon
    {
        $raw = data_get($payload, 'access.valid_until')
            ?? data_get($payload, 'valid_until');

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->where('status', 'authorized')
            ->where(function (Builder $inner) {
                $inner->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            });
    }

    public function isUsable(): bool
    {
        if ($this->status !== 'authorized') {
            return false;
        }

        return $this->valid_until === null || $this->valid_until->isFuture();
    }

    public function daysRemaining(): ?int
    {
        if ($this->valid_until === null) {
            return null;
        }

        $until = $this->valid_until->copy()->timezone('Europe/Amsterdam')->startOfDay();
        $today = now('Europe/Amsterdam')->startOfDay();
        $days = (int) floor($today->diffInDays($until, false));

        return max(0, $days);
    }

    public function toConsentPayload(): array
    {
        return [
            'validUntil' => $this->valid_until?->timezone('Europe/Amsterdam')->toIso8601String(),
            'daysRemaining' => $this->daysRemaining(),
            'expired' => ! $this->isUsable(),
            'aspspName' => $this->aspsp_name,
        ];
    }
}
