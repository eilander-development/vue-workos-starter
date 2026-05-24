<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReportingPeriod
{
    public function periodForMonth(?string $month, int $day = 15): array
    {
        $safeDay = max(1, min(28, $day));
        $selected = $this->resolveMonth($month)->setDay($safeDay)->startOfDay();
        $start = $selected->subMonth();
        $end = $selected->endOfDay();

        return [$start, $end];
    }

    public function configuredStartDay(): int
    {
        return (int) config('finance.reporting.start_day_of_month', 15);
    }

    public function defaultPickerMonth(): string
    {
        $today = CarbonImmutable::now();
        $startDay = max(1, min(28, $this->configuredStartDay()));
        $baseMonth = $today->day >= $startDay ? $today->addMonth() : $today;

        return $baseMonth->format('Y-m');
    }

    public function startOfCurrentMonthFromDay(int $day = 15): CarbonImmutable
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

        $day = (int) config('finance.reporting.start_day_of_month', 15);

        return $this->startOfCurrentMonthFromDay($day);
    }

    public function applyToTransactionQuery(Builder $query): Builder
    {
        return $query->whereDate('date', '>=', $this->startDate()->toDateString());
    }

    private function resolveMonth(?string $month): CarbonImmutable
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
            } catch (\Throwable) {
            }
        }

        return CarbonImmutable::now()->startOfMonth();
    }
}
