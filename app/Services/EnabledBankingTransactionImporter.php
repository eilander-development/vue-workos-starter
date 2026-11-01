<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\BankTransactionIdentity;
use App\Support\BankTransactionStatus;
use App\Support\BankTransactionTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EnabledBankingTransactionImporter
{
    public function __construct(
        protected ImportRuleMatcher $ruleMatcher,
        protected TransactionRuleEnricher $transactionRuleEnricher,
        protected TransactionClassifier $classifier,
        protected BankImportReviewService $reviews,
    ) {}

    public function import(array $transactions): array
    {
        $this->reviews->beginImport();
        $hasSourceTypeColumn = Schema::hasColumn('transactions', 'source_type');
        $hasKeyColumn = Schema::hasColumn('transactions', 'key');
        $stats = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'unmatched' => 0, 'with_time' => 0, 'time_backfilled' => 0, 'blocked' => 0, 'missing_identity' => 0, 'conflicts' => 0, 'pending' => 0, 'pending_booking' => 0, 'invalid' => 0, 'reserved_imported' => 0, 'finalized' => 0, 'archived' => 0, 'ignored_reservations' => 0, 'issues' => []];
        // Index legacy rows too: their source hashes may predate canonical bank identities.
        $known = [];
        foreach (Transaction::query()->where('source_type', 'api')->get() as $tx) {
            $reference = BankTransactionIdentity::reference($tx->bank_payload);
            $account = BankTransactionIdentity::ownAccount($tx->bank_payload, (float) $tx->amount, $tx->account_iban);
            if ($reference && $account !== '') {
                $known[$this->identityHash($account, $reference)][] = $tx;
            }
        }

        $decisions = [];
        foreach (Transaction::onlyHistory()->where('source_type', 'api')->whereIn('archive_reason', ['reservation_matched', 'reservation_expired'])->orderBy('id')->get() as $old) {
            $reference = BankTransactionIdentity::reference($old->bank_payload);
            $account = BankTransactionIdentity::ownAccount($old->bank_payload, (float) $old->amount, $old->account_iban);
            if ($reference && $account !== '') {
                $decisions[$this->identityHash($account, $reference)] = $old;
            }
        }

        $variants = [];
        foreach ($transactions as $row) {
            if (BankTransactionStatus::isPending($row) || ! is_numeric($row['amount'] ?? null)) {
                continue;
            }
            $date = BankTransactionTime::bookingDate($row, fallbackToToday: false);
            $reference = BankTransactionIdentity::reference($row);
            $account = BankTransactionIdentity::ownAccount($row, (float) $row['amount'], $row['account_iban'] ?? null);
            if ($date !== '' && $reference && $account !== '') {
                $variants[$this->identityHash($account, $reference)][$date.'/'.number_format((float) $row['amount'], 2, '.', '')] = true;
            }
        }

        foreach ($transactions as $row) {
            $stats['total']++;
            $pending = BankTransactionStatus::isPending($row);
            $date = BankTransactionTime::bookingDate($row, $pending ? ($row['transaction_date'] ?? data_get($row, 'raw.transaction_date')) : null, fallbackToToday: false);
            if ($date === '' || ! is_numeric($row['amount'] ?? null) || ! is_finite((float) $row['amount'])) {
                $stats['blocked']++;
                $stats['invalid']++;
                $stats['issues'][] = $this->issue($row, 'invalid');

                continue;
            }
            $description = $this->normalizeDescription(trim((string) ($row['description'] ?? $row['merchant'] ?? 'Banktransactie')));
            $iban = $row['counterpart_iban']
                ?? $row['counterparty_iban']
                ?? data_get($row, 'raw.creditor_account.iban')
                ?? data_get($row, 'raw.debtor_account.iban');
            $counterparty = $row['merchant'] ?? $row['counterparty'] ?? null;
            $amount = (float) ($row['amount'] ?? 0);
            $reference = BankTransactionIdentity::reference($row);
            $account = BankTransactionIdentity::ownAccount($row, $amount, $row['account_iban'] ?? null);
            if (! $reference || $account === '') {
                $stats['blocked']++;
                $stats['missing_identity']++;
                $stats['issues'][] = $this->issue($row, 'missing_identity');

                continue;
            }
            $hash = $this->identityHash($account, $reference);
            if ($this->resolvedReservation($decisions[$hash] ?? null, $row, $date, $amount, $pending, $stats)) {
                continue;
            }
            if ($pending) {
                $stats['pending']++;
            }
            $unambiguous = count($variants[$hash] ?? []) === 1;
            $time = $row['time'] ?? BankTransactionTime::extractBookingTime($row);
            $bankPayload = $this->bankPayload($row);
            if ($time) {
                $stats['with_time']++;
            }

            $ids = array_map(fn ($tx) => $tx->id, $known[$hash] ?? []);
            $handled = DB::connection('ledger')->transaction(function () use ($ids, $row, $date, $amount, $pending, $time, $bankPayload, $unambiguous, &$stats) {
                $locked = Transaction::withHistory()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
                $decision = $locked->last(fn ($tx) => $tx->archived_at && in_array($tx->archive_reason, ['reservation_matched', 'reservation_expired'], true));
                if ($decision && $this->resolvedReservation($decision, $row, $date, $amount, $pending, $stats)) {
                    return true;
                }
                $candidates = $locked->filter(fn ($tx) => ! $tx->archived_at)->values()->all();
                if (! $candidates) {
                    return false;
                }
                $this->updateExisting($candidates, $row, $date, $amount, $pending, $time, $bankPayload, $stats, $unambiguous);

                return true;
            });
            if ($handled) {
                continue;
            }

            $classified = $this->classifier->classify($description, $iban, $counterparty, $amount);
            $type = $classified['type'] ?? ($amount < 0 ? 'expense' : ($amount > 0 ? 'income' : null));

            $payload = [
                'source_hash' => $hash,
                'amount' => $amount,
                'description' => $description,
                'counterparty_iban' => $iban,
                'date' => date('Y-m-d', strtotime((string) $date)),
                'type' => $type,
                'category_id' => $classified['category_id'],
                'budget_id' => $classified['budget_id'],
                'rule_id' => $classified['rule_id'],
                'is_pending' => $pending,
                'allocations' => $classified['allocations'] ?? null,
            ];

            if ($hasSourceTypeColumn) {
                $payload['source_type'] = 'api';
            }
            if (Schema::hasColumn('transactions', 'account_iban')) {
                $payload['account_iban'] = $account !== '' ? $account : null;
            }
            if (Schema::hasColumn('transactions', 'counterparty_name')) {
                $payload['counterparty_name'] = $counterparty;
            }
            if ($hasKeyColumn) {
                $payload['key'] = 'tx-'.Str::uuid();
            }
            if (Schema::hasColumn('transactions', 'booked_time')) {
                $payload['booked_time'] = $time;
            }
            if (Schema::hasColumn('transactions', 'link_excluded')) {
                $payload['link_excluded'] = (bool) ($classified['link_excluded'] ?? false);
            }
            if (Schema::hasColumn('transactions', 'link_exclusion_reason')) {
                $payload['link_exclusion_reason'] = $classified['link_exclusion_reason'] ?? null;
            }
            if (Schema::hasColumn('transactions', 'savings_goal_key')) {
                $payload['savings_goal_key'] = $classified['savings_goal_key'] ?? null;
            }
            if ($bankPayload && Schema::hasColumn('transactions', 'bank_payload')) {
                $payload['bank_payload'] = $bankPayload;
            }

            // The unique source_hash index arbitrates simultaneous imports in the database.
            // createOrFirst retries the lookup after a unique-key collision.
            $created = Transaction::query()->createOrFirst(['source_hash' => $hash], $payload);
            $known[$hash] = [$created];
            if (! $created->wasRecentlyCreated) {
                DB::connection('ledger')->transaction(function () use ($created, $row, $date, $amount, $pending, $time, $bankPayload, $unambiguous, &$stats) {
                    $locked = Transaction::query()->whereKey($created->id)->lockForUpdate()->firstOrFail();
                    $this->updateExisting([$locked], $row, $date, $amount, $pending, $time, $bankPayload, $stats, $unambiguous);
                });

                continue;
            }

            if ($classified['budget_id'] || $classified['rule_id']) {
                $stats['matched']++;
            } else {
                $stats['unmatched']++;
            }
            $stats['imported']++;
            if ($pending) {
                $stats['reserved_imported']++;
            } else {
                $this->reviews->observeConfirmed($row, $created, $unambiguous);
            }
        }

        return $stats;
    }

    private function updateExisting(array $rows, array $row, string $date, float $amount, bool $pending, ?string $time, ?array $payload, array &$stats, bool $unambiguous): void
    {
        $lifecycle = new TransactionLifecycle;
        $incompatible = collect($rows)->contains(fn ($tx) => number_format((float) $tx->amount, 2, '.', '') !== number_format($amount, 2, '.', '')
            || (! $pending && ! $tx->is_pending && $tx->date->format('Y-m-d') !== $date));
        if ($incompatible || ! $lifecycle->compatible($rows)) {
            $stats['blocked']++;
            $stats['conflicts']++;
            $stats['issues'][] = $this->issue($row, 'conflict', $rows);

            return;
        }
        // Prefer an existing final booking; a late reservation never downgrades it.
        $keep = collect($rows)->sortBy(fn ($tx) => $tx->is_pending ? 1 : 0)->first();
        $promoting = ! $pending && $keep->is_pending;
        if ($promoting) {
            $lifecycle->rememberReservation($keep);
            $stats['archived']++;
        }
        $lifecycle->mergeLinks($keep, $rows);
        foreach ($rows as $other) {
            if ($other->id === $keep->id) {
                continue;
            }
            $lifecycle->archive($other, $keep, $other->is_pending ? 'reservation_replaced' : 'confirmed_duplicate');
            $stats['archived']++;
        }
        if ($promoting || ($pending && $keep->is_pending)) {
            $keep->fill([
                'date' => $date, 'amount' => $amount, 'is_pending' => $pending,
                'bank_payload' => $payload, 'description' => $this->normalizeDescription(trim((string) ($row['description'] ?? $row['merchant'] ?? $keep->description))),
                'booked_time' => $time,
            ]);
            if ($promoting) {
                $stats['finalized']++;
            }
        } elseif (! $pending) {
            if ($time && blank($keep->booked_time)) {
                $keep->booked_time = $time;
                $stats['time_backfilled']++;
            }
            if (blank($keep->bank_payload)) {
                $keep->bank_payload = $payload;
            }
        }
        $keep->save();
        if (! $pending) {
            $this->reviews->observeConfirmed($row, $keep, $unambiguous);
        }
        if (! $promoting) {
            $stats['duplicates']++;
        }
    }

    private function resolvedReservation(?Transaction $old, array $row, string $date, float $amount, bool $pending, array &$stats): bool
    {
        if (! $old) {
            return false;
        }
        $sameAmount = fn ($a, $b) => number_format((float) $a, 2, '.', '') === number_format((float) $b, 2, '.', '');
        if ($old->archive_reason === 'reservation_expired') {
            // A genuinely final booking is always imported, even after an expiry decision.
            if (! $pending || ! $sameAmount($old->amount, $amount)) {
                return false;
            }
        } else {
            $target = $old->superseded_by_key ? Transaction::query()->forPublicKey($old->superseded_by_key)->first() : null;
            if (! $target || $target->is_pending) {
                return false;
            }
            $matches = $pending
                ? ($sameAmount($old->amount, $amount) || $sameAmount($target->amount, $amount))
                : ($sameAmount($target->amount, $amount) && $target->date->format('Y-m-d') === $date);
            if (! $matches) {
                $stats['blocked']++;
                $stats['conflicts']++;
                $stats['issues'][] = $this->issue($row, 'conflict', [$target]);

                return true;
            }
        }
        $stats['duplicates']++;
        if ($pending) {
            $stats['ignored_reservations']++;
        }

        return true;
    }

    private function issue(array $row, string $code, array $existing = []): array
    {
        $reviewKey = $code === 'conflict' ? $this->reviews->record($row, $existing) : null;
        $amount = is_numeric($row['amount'] ?? null) && is_finite((float) $row['amount']) ? (float) $row['amount'] : null;
        $date = BankTransactionTime::bookingDate($row, fallbackToToday: false) ?: null;
        $reference = BankTransactionIdentity::reference($row);
        $account = BankTransactionIdentity::ownAccount($row, $amount ?? 0, $row['account_iban'] ?? null);
        $missing = [];
        if (! $reference) {
            $missing[] = 'bankreferentie';
        }
        if ($account === '') {
            $missing[] = 'eigen rekeningnummer';
        }
        $invalid = [];
        if (! $date) {
            $invalid[] = 'boekdatum';
        }
        if ($amount === null) {
            $invalid[] = 'bedrag';
        }

        return [
            'code' => $code,
            ...($reviewKey ? ['reviewKey' => $reviewKey] : []),
            'reason' => match ($code) {
                'conflict' => 'Dezelfde bankreferentie en rekening, maar afwijkende boekingsgegevens of tegenstrijdige koppelingen/splitsingen. Niets samengevoegd of overschreven; controle nodig.',
                'missing_identity' => 'Ontbreekt: '.implode(', ', $missing).'. Niet opgeslagen om dubbele boekingen te voorkomen.',
                'invalid' => 'Ontbrekend of ongeldig: '.implode(', ', $invalid).'. Niet opgeslagen.',
                'pending' => BankSyncReport::PENDING_REASON,
                'pending_booking' => BankSyncReport::PENDING_BOOKING_REASON,
            },
            'date' => $date,
            'amount' => $amount,
            'description' => (string) ($row['description'] ?? $row['merchant'] ?? 'Banktransactie'),
            'reference' => $reference,
            'account' => $account ?: null,
            'existing' => array_map(fn ($tx) => [
                'id' => $tx->key ?: 'tx-'.$tx->id,
                'date' => $tx->date?->format('Y-m-d'),
                'amount' => (float) $tx->amount,
                'description' => $tx->description,
                'differences' => array_values(array_filter([
                    $tx->date?->format('Y-m-d') !== $date ? 'boekdatum' : null,
                    number_format((float) $tx->amount, 2, '.', '') !== number_format($amount ?? 0, 2, '.', '') ? 'bedrag' : null,
                ])),
            ], $existing),
        ];
    }

    private function normalizeDescription(string $description): string
    {
        return preg_replace('/^Naam:\s*/iu', '', $description) ?? $description;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function bankPayload(array $row): ?array
    {
        return $row !== [] ? $row : null;
    }

    private function identityHash(string $account, string $reference): string
    {
        return hash('sha256', json_encode(['enablebanking-v2', $account, $reference]));
    }
}
