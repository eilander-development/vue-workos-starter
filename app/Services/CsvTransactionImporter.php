<?php

namespace App\Services;

use App\Models\ImportRule;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CsvTransactionImporter
{
    public function __construct(
        protected ImportRuleMatcher $ruleMatcher,
        protected TransactionRuleEnricher $transactionRuleEnricher,
    ) {}

    public function import(string $path): array
    {
        $startedAt = microtime(true);
        $lines = array_filter(file($path, FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES), fn ($line) => trim($line) !== '');
        $delimiter = isset($lines[0]) && str_contains($lines[0], ';') ? ';' : ',';
        $rows = array_map(fn ($line) => str_getcsv($line, $delimiter), $lines);
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($rows) ?? []);
        $idx = function (array $names) use ($header) {
            foreach ($names as $name) {
                $found = array_search(mb_strtolower($name), $header, true);
                if ($found !== false) {
                    return $found;
                }
            }

            return false;
        };

        $iDate = $idx(['datum']);
        $iAmount = $idx(['bedrag', 'bedrag (eur)']);
        $iDesc = $idx(['naam / omschrijving', 'omschrijving']);
        $iIban = $idx(['tegenrekening']);
        $iAfBij = $idx(['af bij']);
        $rules = ImportRule::with('category')->get();
        $hasSourceTypeColumn = Schema::hasColumn('transactions', 'source_type');

        $stats = ['total' => 0, 'imported' => 0, 'duplicates' => 0, 'matched' => 0, 'unmatched' => 0];

        foreach ($rows as $row) {
            if (! is_array($row) || count($row) < 3) {
                continue;
            }
            $stats['total']++;
            $date = $iDate !== false ? trim((string) ($row[$iDate] ?? '')) : '';
            $amountRaw = $iAmount !== false ? trim((string) ($row[$iAmount] ?? '0')) : '0';
            $afBij = $iAfBij !== false ? mb_strtolower(trim((string) ($row[$iAfBij] ?? ''))) : null;
            $description = $this->normalizeDescription($iDesc !== false ? trim((string) ($row[$iDesc] ?? '')) : '');
            $iban = $iIban !== false ? trim((string) ($row[$iIban] ?? '')) : null;
            $amount = (float) str_replace([','], ['.'], preg_replace('/[^0-9,\.-]/', '', $amountRaw));
            if ($afBij === 'af') {
                $amount = -1 * abs($amount);
            } elseif ($afBij === 'bij') {
                $amount = abs($amount);
            }

            $hash = hash('sha256', implode('|', [$date, $amount, $description, $iban]));
            if (Transaction::where('source_hash', $hash)->exists()) {
                $stats['duplicates']++;

                continue;
            }

            $matchedRule = $this->ruleMatcher->findMatch($rules, $iban, $description);
            $payload = [
                'source_hash' => $hash,
                'amount' => $amount,
                'description' => $description,
                'counterparty_iban' => $iban,
                'date' => $this->normalizeDate($date),
                'type' => $amount < 0 ? 'expense' : ($amount > 0 ? 'income' : null),
            ];
            if ($hasSourceTypeColumn) {
                $payload['source_type'] = 'csv';
            }

            if ($matchedRule) {
                $payload = $this->transactionRuleEnricher->enrich($payload, $matchedRule);
                $stats['matched']++;
            } else {
                $stats['unmatched']++;
            }

            Transaction::create($payload);
            $stats['imported']++;
        }

        Log::info('CSV transaction import completed', [
            'path' => $path,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'summary' => $stats,
        ]);

        return $stats;
    }

    private function normalizeDate(?string $date): string
    {
        $value = trim((string) $date);
        if ($value === '') {
            return now()->format('Y-m-d');
        }
        if (preg_match('/^\d{8}$/', $value)) {
            return substr($value, 0, 4).'-'.substr($value, 4, 2).'-'.substr($value, 6, 2);
        }

        return date('Y-m-d', strtotime($value));
    }

    private function normalizeDescription(string $description): string
    {
        return preg_replace('/^Naam:\s*/iu', '', $description) ?? $description;
    }
}
