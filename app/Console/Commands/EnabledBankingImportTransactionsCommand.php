<?php

namespace App\Console\Commands;

use App\Services\EnableBanking;
use App\Services\EnabledBankingTransactionImporter;
use Illuminate\Console\Command;

class EnabledBankingImportTransactionsCommand extends Command
{
    protected $signature = 'enabled-banking:import-transactions {--days=365}';

    protected $description = 'Importeer EnableBanking transacties en pas opgeslagen regels toe (standaard laatste 365 dagen).';

    public function handle(EnableBanking $enableBanking, EnabledBankingTransactionImporter $importer): int
    {
        $days = max(1, (int) $this->option('days'));
        $sessionId = session('eb_session_id');

        if (! $sessionId) {
            $this->error('Geen actieve EnableBanking sessie gevonden in de huidige Laravel sessie.');
            $this->line('Tip: open eerst /enabled-banking in dezelfde omgeving en koppel de bank.');

            return self::FAILURE;
        }

        $sessionData = $enableBanking->getSessionData($sessionId);
        $accounts = $sessionData['accounts_data'] ?? ($sessionData['accounts'] ?? []);

        if (empty($accounts)) {
            $this->warn('Geen gekoppelde rekeningen gevonden.');

            return self::SUCCESS;
        }

        $dateFrom = now()->subDays($days)->format('Y-m-d');
        $allTransactions = [];

        foreach ($accounts as $account) {
            $accountId = $account['uid'] ?? null;
            if (! $accountId) {
                continue;
            }

            $this->line("Ophalen transacties voor account {$accountId} vanaf {$dateFrom}...");
            $transactions = $enableBanking->getAllTransactions($accountId, ['date_from' => $dateFrom]);
            $allTransactions = array_merge($allTransactions, $transactions);
        }

        if (empty($allTransactions)) {
            $this->warn('Geen transacties opgehaald voor import.');

            return self::SUCCESS;
        }

        $stats = $importer->import($allTransactions);
        $this->info('Import klaar.');
        $this->table(
            ['total', 'imported', 'duplicates', 'matched', 'unmatched'],
            [[$stats['total'], $stats['imported'], $stats['duplicates'], $stats['matched'], $stats['unmatched']]]
        );

        return self::SUCCESS;
    }
}

