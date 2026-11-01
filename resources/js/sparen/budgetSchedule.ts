import type { BudgetItem, BudgetMonthEntry, MonthlyBudget } from './types';

export interface BudgetScheduleCell {
    month: string;
    monthId: string;
    monthName: string;
    year: number;
    amount: number;
    entries: BudgetMonthEntry[];
    sourceMonth: string | null;
    origin: 'saved' | 'forecast' | 'derived';
}
export interface BudgetYearSchedule {
    year: number;
    period: 'past' | 'current' | 'future';
    months: BudgetScheduleCell[];
}
export interface BudgetSchedules {
    currentYear: number;
    revision: string;
    schedules: BudgetYearSchedule[];
}
export function scheduleAsMonths(
    schedule: BudgetYearSchedule,
    item: BudgetItem,
): MonthlyBudget[] {
    return schedule.months.map((month) => ({
        monthId: month.monthId,
        monthName: month.monthName,
        year: month.year,
        opRekening: 0,
        items: [
            {
                ...item,
                actual: month.amount,
                estimated: month.amount,
                monthEntries: month.entries,
            },
        ],
    }));
}
