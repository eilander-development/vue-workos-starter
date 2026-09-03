<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\CheckingPeriodSnapshot;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CheckingBalanceSnapshotService
{
    public function __construct(
        protected ReportingPeriod $reportingPeriod,
    ) {}

    /**
     * Freeze checking balances for closed reporting months. Existing rows are never overwritten.
     *
     * @param  Collection<int, Transaction>  $transactions
     * @return array<string, array{balance: ?float, captured: bool}>
     */
    public function syncYear(int $year, ?BankAccount $checking, Collection $transactions): array
    {
        $empty = [];
        foreach (array_keys(SparenStateService::MONTHS) as $monthId) {
            $empty[$monthId] = ['balance' => null, 'captured' => false];
        }

        if (! Schema::hasTable('checking_period_snapshots')) {
            return $empty;
        }

        $today = CarbonImmutable::now('Europe/Amsterdam');
        $current = $this->reportingPeriod->defaultSparenMonth($today);
        $currentIndex = $this->monthIndex($current['monthId'], $current['year']);
        $live = $checking ? (float) $checking->balance : 0.0;

        $txs = $transactions->filter(function (Transaction $tx) {
            return ! (bool) $tx->is_pending;
        });

        $firstTxDate = $txs
            ->map(fn (Transaction $tx) => optional($tx->date)->toDateString())
            ->filter()
            ->min() ?? '9999-12-31';

        $result = [];

        foreach (array_keys(SparenStateService::MONTHS) as $monthId) {
            [$periodStart, $periodEnd] = $this->reportingPeriod->periodForSparenMonth($monthId, $year);
            unset($periodStart);
            $index = $this->monthIndex($monthId, $year);
            $endDate = $periodEnd->toDateString();

            if ($index === $currentIndex) {
                $result[$monthId] = ['balance' => $live, 'captured' => false];
                continue;
            }

            if ($index > $currentIndex) {
                $result[$monthId] = ['balance' => 0.0, 'captured' => false];
                continue;
            }

            if ($endDate < $firstTxDate) {
                $result[$monthId] = ['balance' => null, 'captured' => false];
                continue;
            }

            $existing = CheckingPeriodSnapshot::query()
                ->where('year', $year)
                ->where('month_id', $monthId)
                ->first();

            if ($existing) {
                $result[$monthId] = [
                    'balance' => (float) $existing->balance,
                    'captured' => true,
                ];
                continue;
            }

            if (! $checking) {
                $result[$monthId] = ['balance' => null, 'captured' => false];
                continue;
            }

            $afterEnd = $txs->filter(function (Transaction $tx) use ($endDate) {
                $date = optional($tx->date)->toDateString();

                return $date !== null && $date > $endDate;
            });
            $balance = round($live - (float) $afterEnd->sum(fn (Transaction $tx) => (float) $tx->amount), 2);

            $snapshot = CheckingPeriodSnapshot::query()->firstOrCreate(
                [
                    'year' => $year,
                    'month_id' => $monthId,
                ],
                [
                    'period_end' => $endDate,
                    'balance' => $balance,
                    'source' => 'reconstructed',
                    'captured_at' => now(),
                ]
            );

            $result[$monthId] = [
                'balance' => (float) $snapshot->balance,
                'captured' => true,
            ];
        }

        return $result;
    }

    private function monthIndex(string $monthId, int $year): int
    {
        $number = array_search($monthId, array_keys(SparenStateService::MONTHS), true);

        return $year * 12 + ($number === false ? 1 : $number + 1);
    }
}
