<?php

namespace App\Services;

use App\Models\Transaction;
use App\Support\BankTransactionIdentity;
use App\Support\BankTransactionTime;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EnabledBankingTransactionImporter
{
    public function __construct(
        protected ImportRuleMatcher $ruleMatcher,
        protected TransactionRuleEnricher $transactionRuleEnricher,
        protected TransactionClassifier $classifier,
    ) {}

    public function import(array $transactions): array
    {
        $hasSourceTypeColumn = Schema::hasColumn('transactions', 'source_type');
        $hasKeyColumn = Schema::hasColumn('transactions', 'key');
        $stats = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'unmatched' => 0, 'with_time' => 0, 'time_backfilled' => 0, 'blocked' => 0, 'missing_identity' => 0, 'conflicts' => 0, 'pending' => 0, 'invalid' => 0];
        // Index legacy rows too: their source hashes may predate canonical bank identities.
        $known = [];
        foreach (Transaction::query()->where('source_type', 'api')->get() as $tx) {
            $reference = BankTransactionIdentity::reference($tx->bank_payload);
            $account = BankTransactionIdentity::ownAccount($tx->bank_payload, (float) $tx->amount, $tx->account_iban);
            if ($reference && $account !== '') {
                $known[$this->identityHash($account, $reference)][] = $tx;
            }
        }

        foreach ($transactions as $row) {
            $stats['total']++;
            $date = BankTransactionTime::bookingDate($row, fallbackToToday: false);
            if ($date === '' || ! is_numeric($row['amount'] ?? null) || ! is_finite((float) $row['amount'])) {
                $stats['blocked']++;
                $stats['invalid']++;

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

                continue;
            }
            if ($this->isPending($row)) {
                $stats['pending']++;

                continue;
            }
            $hash = $this->identityHash($account, $reference);
            $time = $row['time'] ?? BankTransactionTime::extractBookingTime($row);
            $bankPayload = $this->bankPayload($row);
            if ($time) {
                $stats['with_time']++;
            }

            $candidates = $known[$hash] ?? [];
            if (collect($candidates)->contains(fn ($tx) => ! $this->sameBooking($tx, $date, $amount))) {
                $stats['blocked']++;
                $stats['conflicts']++;

                continue;
            }
            $existing = $candidates[0] ?? null;
            if ($existing) {
                $stats['duplicates']++;
                $dirty = false;
                if ($time && blank($existing->booked_time)) {
                    $existing->booked_time = $time;
                    $dirty = true;
                    $stats['time_backfilled']++;
                }
                if ($bankPayload && Schema::hasColumn('transactions', 'bank_payload') && blank($existing->bank_payload)) {
                    $existing->bank_payload = $bankPayload;
                    $dirty = true;
                }
                if ($dirty) {
                    $existing->save();
                }

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
                if ($this->sameBooking($created, $date, $amount)) {
                    $stats['duplicates']++;
                } else {
                    $stats['blocked']++;
                    $stats['conflicts']++;
                }

                continue;
            }

            if ($classified['budget_id'] || $classified['rule_id']) {
                $stats['matched']++;
            } else {
                $stats['unmatched']++;
            }
            $stats['imported']++;
        }

        return $stats;
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

    private function sameBooking(Transaction $tx, string $date, float $amount): bool
    {
        return $tx->date?->format('Y-m-d') === $date
            && number_format((float) $tx->amount, 2, '.', '') === number_format($amount, 2, '.', '');
    }

    private function isPending(array $row): bool
    {
        if (! empty($row['is_pending']) || ! empty($row['isPending'])) {
            return true;
        }
        $status = strtoupper((string) ($row['status'] ?? ''));
        if (in_array($status, ['PDNG', 'PENDING', 'INFO'], true)) {
            return true;
        }

        return is_array($row['raw'] ?? null) && $this->isPending($row['raw']);
    }
}
