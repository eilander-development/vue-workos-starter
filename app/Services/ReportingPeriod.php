<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ReportingPeriod
{
    public const SPAREN_MONTH_IDS = [
        'jan', 'feb', 'mrt', 'apr', 'mei', 'jun',
        'jul', 'aug', 'sep', 'okt', 'nov', 'dec',
    ];

    public function periodForMonth(?string $month, int $day = 15): array
    {
        $safeDay = max(1, min(28, $day));
        $selected = $this->resolveMonth($month)->setDay($safeDay)->startOfDay();
        $start = $selected->subMonth();
        $end = $selected->endOfDay();

        return [$start, $end];
    }

    /**
     * Sparen labels months by their start date (e.g. "augustus" = 15 aug – 14 sep).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function periodForSparenMonth(string $monthId, int $year, ?int $day = null): array
    {
        $safeDay = max(1, min(28, $day ?? $this->configuredStartDay()));
        $monthNumber = $this->sparenMonthNumber($monthId);
        $start = CarbonImmutable::create($year, $monthNumber, $safeDay, 0, 0, 0, 'Europe/Amsterdam')->startOfDay();
        $end = $start->addMonth()->subDay()->endOfDay();

        return [$start, $end];
    }

    /**
     * @return array{monthId: string, year: int}
     */
    public function defaultSparenMonth(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::now('Europe/Amsterdam');
        $anchor = $today->day >= $this->configuredStartDay() ? $today : $today->subMonthNoOverflow();

        return [
            'monthId' => $this->sparenMonthIdFromNumber((int) $anchor->month),
            'year' => (int) $anchor->year,
        ];
    }

    public function transactionInSparenMonth(string $date, string $monthId, int $year, ?int $day = null): bool
    {
        [$start, $end] = $this->periodForSparenMonth($monthId, $year, $day);
        $parsed = CarbonImmutable::parse($date, 'Europe/Amsterdam')->startOfDay();

        return $parsed->greaterThanOrEqualTo($start) && $parsed->lessThanOrEqualTo($end);
    }

    public function isCurrentSparenMonth(string $monthId, int $year): bool
    {
        $current = $this->defaultSparenMonth();

        return $current['monthId'] === $monthId && $current['year'] === $year;
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

    private function sparenMonthNumber(string $monthId): int
    {
        $index = array_search($monthId, self::SPAREN_MONTH_IDS, true);

        return $index === false ? 1 : $index + 1;
    }

    private function sparenMonthIdFromNumber(int $monthNumber): string
    {
        return self::SPAREN_MONTH_IDS[max(0, min(11, $monthNumber - 1))];
    }
}
