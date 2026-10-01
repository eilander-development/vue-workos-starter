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

    public static function ownAccount(?array $payload, float $amount, ?string $explicit = null): string
    {
        $account = self::account($explicit);
        if ($account !== '') {
            return $account;
        }
        $payload ??= [];
        foreach (['account_iban'] as $field) {
            $candidate = self::account(is_string($payload[$field] ?? null) ? $payload[$field] : null);
            if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $candidate)) {
                return $candidate;
            }
        }
        if (is_array($payload['raw'] ?? null)) {
            $nested = self::ownAccount($payload['raw'], $amount);
            if ($nested !== '') {
                return $nested;
            }
        }
        // On the checking account, incoming money belongs to the creditor;
        // outgoing money belongs to the debtor. Never use the counterparty.
        $field = $amount >= 0 ? 'creditor_account.iban' : 'debtor_account.iban';
        $candidate = data_get($payload, $field);

        $account = self::account(is_string($candidate) ? $candidate : null);
        if ($account !== '') {
            return $account;
        }
        $fallback = self::account(is_string($payload['account_id'] ?? null) ? $payload['account_id'] : null);

        return preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/', $fallback) ? $fallback : '';
    }

    public static function key(Transaction $tx): ?string
    {
        $ref = self::reference($tx->bank_payload);
        $account = self::ownAccount($tx->bank_payload, (float) $tx->amount, $tx->account_iban);
        if ($tx->source_type !== 'api' || $tx->is_pending || ! $ref || $account === '') {
            return null;
        }

        return json_encode([$account, $ref, $tx->date?->format('Y-m-d'), number_format((float) $tx->amount, 2, '.', '')]);
    }
}
