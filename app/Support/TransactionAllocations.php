<?php

namespace App\Support;

class TransactionAllocations
{
    /**
     * @param  mixed  $rows
     * @return list<array{budgetItemId: string, amount: float}>
     */
    public static function normalize(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $clean = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $budgetItemId = trim((string) ($row['budgetItemId'] ?? ''));
            $amount = round(abs((float) ($row['amount'] ?? 0)), 2);
            if ($budgetItemId === '' || $amount <= 0) {
                continue;
            }

            $clean[] = [
                'budgetItemId' => $budgetItemId,
                'amount' => $amount,
            ];
        }

        return count($clean) >= 2 ? array_values($clean) : [];
    }

    /**
     * @param  list<array{budgetItemId: string, amount: float}>|mixed  $rows
     * @return list<array{budgetItemId: string, amount: float}>
     */
    public static function scaleToAmount(mixed $rows, float $absAmount): array
    {
        $clean = self::normalize($rows);
        $total = round(abs($absAmount), 2);
        if ($clean === [] || $total <= 0) {
            return [];
        }

        $sum = round(array_sum(array_column($clean, 'amount')), 2);
        if ($sum <= 0) {
            return [];
        }

        if (abs($sum - $total) < 0.005) {
            return $clean;
        }

        $scaled = [];
        $remaining = $total;
        $lastIndex = count($clean) - 1;

        foreach ($clean as $index => $row) {
            if ($index === $lastIndex) {
                $scaled[] = [
                    'budgetItemId' => $row['budgetItemId'],
                    'amount' => round($remaining, 2),
                ];
                continue;
            }

            $amount = round($row['amount'] / $sum * $total, 2);
            $remaining = round($remaining - $amount, 2);
            $scaled[] = [
                'budgetItemId' => $row['budgetItemId'],
                'amount' => $amount,
            ];
        }

        return $scaled;
    }

    /**
     * @param  list<array{budgetItemId: string, amount: float}>  $rows
     */
    public static function amountToward(array $rows, string $budgetItemId, float $fallbackAmount): float
    {
        $clean = self::normalize($rows);
        if ($clean === []) {
            return round(abs($fallbackAmount), 2);
        }

        foreach ($clean as $row) {
            if ($row['budgetItemId'] === $budgetItemId) {
                return $row['amount'];
            }
        }

        return 0.0;
    }

    /**
     * @param  list<array{budgetItemId: string, amount: float}>  $rows
     */
    public static function includes(array $rows, string $budgetItemId): bool
    {
        foreach (self::normalize($rows) as $row) {
            if ($row['budgetItemId'] === $budgetItemId) {
                return true;
            }
        }

        return false;
    }
}
