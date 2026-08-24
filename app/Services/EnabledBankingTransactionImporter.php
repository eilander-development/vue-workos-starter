<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\ImportRule;
use App\Models\Transaction;
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
        $stats = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'unmatched' => 0, 'with_time' => 0, 'time_backfilled' => 0];

        foreach ($transactions as $row) {
            $stats['total']++;
            $date = $row['posted_at'] ?? $row['date'] ?? now()->format('Y-m-d');
            $description = $this->normalizeDescription(trim((string) ($row['description'] ?? $row['merchant'] ?? 'Banktransactie')));
            $iban = $row['counterpart_iban']
                ?? $row['counterparty_iban']
                ?? data_get($row, 'raw.creditor_account.iban')
                ?? data_get($row, 'raw.debtor_account.iban');
            $counterparty = $row['merchant'] ?? $row['counterparty'] ?? null;
            $amount = (float) ($row['amount'] ?? 0);
            $hash = $this->sourceHash($row, $date, $amount, $description, $iban);
            $time = $row['time'] ?? BankTransactionTime::extract($row);
            if ($time) {
                $stats['with_time']++;
            }

            $existing = Transaction::query()->where('source_hash', $hash)->first();
            if ($existing) {
                $stats['duplicates']++;
                if ($time && blank($existing->booked_time)) {
                    $existing->booked_time = $time;
                    $existing->save();
                    $stats['time_backfilled']++;
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
                $payload['account_iban'] = $row['account_iban'] ?? $row['account_id'] ?? null;
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

            Transaction::query()->create($payload);

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

    private function sourceHash(array $row, mixed $date, float $amount, string $description, mixed $iban): string
    {
        $raw = is_array($row['raw'] ?? null) ? $row['raw'] : [];
        $bankId = $raw['entry_reference']
            ?? $raw['transaction_id']
            ?? null;

        $parts = $bankId
            ? ['ref', (string) $bankId]
            : [(string) $date, number_format($amount, 2, '.', ''), $description, (string) $iban];

        return hash('sha256', implode('|', $parts));
    }
}
