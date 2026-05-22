<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReportingPeriod
{
    public function startOfCurrentMonthFromDay(int $day = 20): CarbonImmutable
    {
        $safeDay = max(1, min(28, $day));

        return CarbonImmutable::now()->startOfMonth()->setDay($safeDay)->startOfDay();
    }

    public function startDate(): CarbonImmutable
    {
        $anchorTransactionId = config('finance.reporting.anchor_transaction_id');
        if ($anchorTransactionId) {
            $anchorDate = Transaction::query()
                ->whereKey($anchorTransactionId)
                ->value('date');

            if ($anchorDate) {
                return CarbonImmutable::parse($anchorDate)->startOfDay();
            }
        }

        $day = (int) config('finance.reporting.start_day_of_month', 20);

        return $this->startOfCurrentMonthFromDay($day);
    }

    public function applyToTransactionQuery(Builder $query): Builder
    {
        return $query->whereDate('date', '>=', $this->startDate()->toDateString());
    }
}
