<?php

namespace App\Support;

use App\Models\Transaction;

class BankTransactionIdentity
{
    public static function reference(?array $payload): ?string
    {
        if (! $payload) {
            return null;
        }
        if (is_array($payload['raw'] ?? null)) {
            $nested = self::reference($payload['raw']);
            if ($nested !== null) {
                return $nested;
            }
        }
        foreach (['entry_reference', 'transaction_id'] as $field) {
            $value = $payload[$field] ?? null;
            if ((is_string($value) || is_int($value)) && trim((string) $value) !== '' && ! in_array(strtoupper(trim((string) $value)), ['NOTPROVIDED', 'NOT PROVIDED', 'UNKNOWN'], true)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    public static function account(?string $account): string
    {
        return strtoupper(preg_replace('/\s+/', '', $account ?? '') ?? '');
    }

    public static function key(Transaction $tx): ?string
    {
        $ref = self::reference($tx->bank_payload);
        $account = self::account($tx->account_iban);
        if ($tx->source_type !== 'api' || $tx->is_pending || ! $ref || $account === '') {
            return null;
        }

        return json_encode([$account, $ref, $tx->date?->format('Y-m-d'), number_format((float) $tx->amount, 2, '.', '')]);
    }
}
