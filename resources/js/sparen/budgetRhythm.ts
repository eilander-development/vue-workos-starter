import { amountTowardBudgetItem } from './allocations';
import { transactionCountsTowardBudgetItem } from './budgetPayment';
import { isBookedTransaction } from './transactionStatus';
import type { BudgetItem, Transaction } from './types';

export interface BudgetRhythmMonth {
    month: string;
    monthId: string;
    year: number;
    amount: number;
    sourceMonth: string | null;
    periodStart: string;
    periodEnd: string;
    through: string;
    complete: boolean;
}
export type BudgetRhythmScope = { month: string } | { year: number } | null;

export function rhythmMonthsInScope(
    months: BudgetRhythmMonth[],
    scope: BudgetRhythmScope,
) {
    return months.filter(
        (month) =>
            !scope ||
            ('month' in scope
                ? month.month === scope.month
                : month.year === scope.year),
    );
}

export function budgetRhythmRows(
    item: BudgetItem,
    transactions: Transaction[],
    months: BudgetRhythmMonth[],
) {
    return transactions
        .flatMap((tx) => {
            if (!transactionCountsTowardBudgetItem(tx, item)) return [];
            const month = months.find(
                (month) =>
                    tx.date.slice(0, 10) >= month.periodStart &&
                    tx.date.slice(0, 10) <= month.through,
            );
            return month
                ? [
                      {
                          tx,
                          month: month.month,
                          amount: amountTowardBudgetItem(tx, item.id),
                      },
                  ]
                : [];
        })
        .sort(
            (a, b) =>
                b.tx.date.localeCompare(a.tx.date) ||
                (b.tx.time ?? '').localeCompare(a.tx.time ?? '') ||
                b.tx.id.localeCompare(a.tx.id),
        );
}

export function budgetRhythmTotals(
    rows: ReturnType<typeof budgetRhythmRows>,
    months: BudgetRhythmMonth[],
) {
    return months.map((month) => ({
        ...month,
        actual:
            Math.round(
                rows
                    .filter(
                        (row) =>
                            row.month === month.month &&
                            isBookedTransaction(row.tx),
                    )
                    .reduce(
                        (sum, row) => sum + Math.round(row.amount * 100),
                        0,
                    ),
            ) / 100,
        count: rows.filter(
            (row) => row.month === month.month && isBookedTransaction(row.tx),
        ).length,
    }));
}
