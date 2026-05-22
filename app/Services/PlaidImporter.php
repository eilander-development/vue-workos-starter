<?php

namespace App\Services;

use App\Models\ImportRule;
use App\Models\Transaction;

class PlaidImporter
{
    public function import(array $transactions): array
    {
        $rules = ImportRule::all();
        $summary = [
            'total' => count($transactions),
            'imported' => 0,
            'duplicates' => 0,
            'matched' => 0,
            'unmatched' => 0,
        ];

        foreach ($transactions as $transaction) {
            $hash = $this->makeSourceHash($transaction);

            if (Transaction::where('source_hash', $hash)->exists()) {
                $summary['duplicates']++;
                continue;
            }

            $payload = $this->buildPayload($transaction, $hash);
            $matchedRule = $this->findMatchingRule($rules, $payload);

            if ($matchedRule) {
                $payload['category_id'] = $matchedRule->category_id;
                $payload['budget_id'] = $matchedRule->budget_id;
                $payload['rule_id'] = $matchedRule->id;
                $category = $matchedRule->category;
                $payload['type'] = $category?->type ?? $payload['type'];
                $payload['icon'] = $category?->icon;
                $payload['color'] = $category?->color;
                $summary['matched']++;
            } else {
                $summary['unmatched']++;
            }

            Transaction::create($payload);
            $summary['imported']++;
        }

        return $summary;
    }

    protected function makeSourceHash(array $transaction): string
    {
        return hash('sha256', implode('|', [
            $transaction['transaction_id'] ?? '',
            $transaction['date'] ?? '',
            $transaction['amount'] ?? '',
            $transaction['name'] ?? '',
            $transaction['account_id'] ?? '',
        ]));
    }

    protected function buildPayload(array $transaction, string $hash): array
    {
        $description = $transaction['name'] ?? $transaction['merchant_name'] ?? $transaction['transaction_type'] ?? 'Banktransactie';
        $type = $this->resolveTransactionType($transaction);

        return [
            'source_hash' => $hash,
            'amount' => (float) ($transaction['amount'] ?? 0),
            'description' => $description,
            'counterparty_iban' => $transaction['account_id'] ?? null,
            'date' => $transaction['date'] ?? now()->format('Y-m-d'),
            'type' => $type,
        ];
    }

    protected function resolveTransactionType(array $transaction): string|null
    {
        $transactionType = strtolower((string) ($transaction['transaction_type'] ?? ''));

        if ($transactionType === 'credit') {
            return 'income';
        }

        if ($transactionType === 'debit') {
            return 'expense';
        }

        return $transaction['amount'] < 0 ? 'expense' : 'income';
    }

    protected function findMatchingRule($rules, array $payload)
    {
        return $rules->first(function (ImportRule $rule) use ($payload) {
            if ($rule->type === 'iban') {
                return $payload['counterparty_iban'] && str_contains(mb_strtolower($payload['counterparty_iban']), mb_strtolower($rule->match_value));
            }

            return str_contains(mb_strtolower($payload['description']), mb_strtolower($rule->match_value));
        });
    }
}
