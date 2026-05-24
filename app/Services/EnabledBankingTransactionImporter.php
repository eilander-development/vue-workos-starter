<?php

namespace App\Services;

use App\Models\ImportRule;
use App\Models\Transaction;
use Illuminate\Support\Facades\Schema;

class EnabledBankingTransactionImporter
{
    public function __construct(
        protected ImportRuleMatcher $ruleMatcher,
        protected TransactionRuleEnricher $transactionRuleEnricher,
    ) {}

    public function import(array $transactions): array
    {
        $rules = ImportRule::with('category')->get();
        $hasSourceTypeColumn = Schema::hasColumn('transactions', 'source_type');
        $stats = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'unmatched' => 0];

        foreach ($transactions as $row) {
            $stats['total']++;
            $date = $row['posted_at'] ?? $row['date'] ?? now()->format('Y-m-d');
            $description = $this->normalizeDescription(trim((string) ($row['description'] ?? $row['merchant'] ?? 'Banktransactie')));
            $iban = $row['counterparty_iban']
                ?? data_get($row, 'raw.creditor_account.iban')
                ?? data_get($row, 'raw.debtor_account.iban');
            $amount = (float) ($row['amount'] ?? 0);
            $hash = hash('sha256', implode('|', [$date, $amount, $description, $iban]));

            if (Transaction::where('source_hash', $hash)->exists()) {
                $stats['duplicates']++;
                continue;
            }

            $payload = [
                'source_hash' => $hash,
                'amount' => $amount,
                'description' => $description,
                'counterparty_iban' => $iban,
                'date' => date('Y-m-d', strtotime($date)),
                'type' => $amount < 0 ? 'expense' : ($amount > 0 ? 'income' : null),
            ];
            if ($hasSourceTypeColumn) {
                $payload['source_type'] = 'api';
            }

            $matchedRule = $this->ruleMatcher->findMatch($rules, $iban, $description);
            if ($matchedRule) {
                $payload = $this->transactionRuleEnricher->enrich($payload, $matchedRule);
                $stats['matched']++;
            } else {
                $stats['unmatched']++;
            }

            Transaction::create($payload);
            $stats['imported']++;
        }

        return $stats;
    }

    private function normalizeDescription(string $description): string
    {
        return preg_replace('/^Naam:\s*/iu', '', $description) ?? $description;
    }
}
