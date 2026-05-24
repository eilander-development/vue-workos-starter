<?php

namespace App\Services;

class EnableBankingDataService
{
    public function buildTransactionQueryParams(?string $days, ?string $dateFrom, ?string $from, ?string $dateTo, ?string $to): array
    {
        if ($days && is_numeric($days)) {
            $resolvedDateFrom = date('Y-m-d', strtotime('-'.(int) $days.' days'));
        } else {
            $resolvedDateFrom = $dateFrom ?? $from ?? date('Y-m-d', strtotime('-30 days'));
        }

        return array_filter([
            'date_from' => $resolvedDateFrom,
            'date_to' => $dateTo ?? $to,
        ]);
    }

    public function mapAccountBalances(array $accounts, EnableBanking $client): array
    {
        $balances = [];

        foreach ($accounts as $account) {
            $accountId = $account['uid'] ?? null;
            $liveBalance = 0.0;

            if ($accountId) {
                try {
                    $balanceData = $client->getBalances($accountId);
                    $balanceObject = $balanceData['balances'][0] ?? null;
                    $liveBalance = $balanceObject['balance_amount']['amount'] ?? ($balanceObject['amount'] ?? 0.0);
                } catch (\Exception $e) {
                    \Log::error("Kon saldo niet laden voor account {$accountId}: ".$e->getMessage());
                }
            }

            $balances[] = [
                'accountId' => $accountId,
                'name' => $account['account_id']['iban'] ?? ($account['name'] ?? 'Bankrekening'),
                'currency' => $account['currency'] ?? 'EUR',
                'balance' => (float) $liveBalance,
                'available' => (float) $liveBalance,
                'raw' => $account,
            ];
        }

        return $balances;
    }
}

