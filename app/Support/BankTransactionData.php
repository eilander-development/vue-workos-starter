<?php

namespace App\Support;

class BankTransactionData
{
    /** Bank-owned fields only. Budget links and splits belong to the existing record. */
    public static function attributes(array $row): array
    {
        $amount = (float) $row['amount'];
        $description = trim((string) ($row['description'] ?? $row['merchant'] ?? 'Banktransactie'));
        $iban = $row['counterpart_iban'] ?? $row['counterparty_iban']
            ?? data_get($row, $amount < 0 ? 'raw.creditor_account.iban' : 'raw.debtor_account.iban');

        return [
            'date' => BankTransactionTime::bookingDate($row, fallbackToToday: false),
            'amount' => $amount,
            'description' => mb_substr(preg_replace('/^Naam:\s*/iu', '', $description) ?? $description, 0, 255),
            'account_iban' => BankTransactionIdentity::ownAccount($row, $amount, $row['account_iban'] ?? null),
            'counterparty_iban' => $iban,
            'counterparty_name' => isset($row['merchant']) || isset($row['counterparty']) ? mb_substr((string) ($row['merchant'] ?? $row['counterparty']), 0, 255) : null,
            'booked_time' => $row['time'] ?? BankTransactionTime::extractBookingTime($row),
            'is_pending' => false,
            'bank_payload' => $row,
        ];
    }
}
