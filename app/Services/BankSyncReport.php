<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\BankTransactionIdentity;
use App\Support\BankTransactionStatus;

class BankSyncReport
{
    public const PENDING_REASON = 'Je betaling is bij de bank bekend, maar heeft nog geen definitieve boeking. Daarom is deze nog niet in de begroting verwerkt. Je hoeft niets te doen: zodra de bank de betaling definitief boekt, wordt deze bij een volgende synchronisatie opgehaald.';

    public const PENDING_BOOKING_REASON = 'Deze betaling is eerder als voorlopig opgeslagen. De bank levert nu de definitieve boeking met een andere boekdatum aan; rekening, bankreferentie en bedrag zijn gelijk. De oude voorlopige registratie moet nog met de definitieve boeking worden verrekend. De app heeft niets aangepast of toegevoegd. Alleen wachten op een volgende synchronisatie lost deze oude registratie niet op.';

    /** Only date changes from proven provisional entries belong to pending processing. */
    public static function needsBookingReconciliation(array $transactions, string $date, float $amount): bool
    {
        $hasProvisionalDateChange = false;
        foreach ($transactions as $tx) {
            if (number_format((float) $tx->amount, 2, '.', '') !== number_format($amount, 2, '.', '')) {
                return false;
            }
            if ($tx->date?->format('Y-m-d') === $date) {
                continue;
            }
            if (! $tx->is_pending && ! BankTransactionStatus::isPending($tx->bank_payload ?? [])) {
                return false;
            }
            $hasProvisionalDateChange = true;
        }

        return $hasProvisionalDateChange;
    }

    /** Also explain old session reports without requiring another bank sync. */
    public function normalize(?array $report): ?array
    {
        if ($report === null) {
            return null;
        }

        foreach ($report['issues'] ?? [] as $index => $issue) {
            if ($issue['code'] === 'pending') {
                $report['issues'][$index]['reason'] = self::PENDING_REASON;

                continue;
            }
            if ($issue['code'] !== 'conflict' || empty($issue['existing'])
                || empty($issue['date']) || ! is_numeric($issue['amount'] ?? null)
                || empty($issue['reference']) || empty($issue['account'])) {
                continue;
            }

            $keys = array_column($issue['existing'], 'id');
            $transactions = Transaction::query()->whereIn('key', $keys)->get();
            if ($transactions->count() !== count($issue['existing']) || $transactions->isEmpty()) {
                continue;
            }
            $sameIdentity = $transactions->every(fn (Transaction $tx) => $tx->source_type === 'api'
                && BankTransactionIdentity::reference($tx->bank_payload) === $issue['reference']
                && BankTransactionIdentity::ownAccount($tx->bank_payload, (float) $tx->amount, $tx->account_iban) === $issue['account']);
            if (! $sameIdentity || ! self::needsBookingReconciliation($transactions->all(), $issue['date'], (float) $issue['amount'])) {
                continue;
            }

            $report['issues'][$index]['code'] = 'pending_booking';
            $report['issues'][$index]['reason'] = self::PENDING_BOOKING_REASON;
            $report['blocked'] = max(0, ($report['blocked'] ?? 0) - 1);
            $report['conflicts'] = max(0, ($report['conflicts'] ?? 0) - 1);
            $report['pending'] = ($report['pending'] ?? 0) + 1;
            $report['pending_booking'] = ($report['pending_booking'] ?? 0) + 1;
        }

        return $report;
    }
}
