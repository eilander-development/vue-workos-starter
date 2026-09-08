import { transactionCountsTowardBudgetItem } from "./budgetPayment";
import {
  MONTH_ID_TO_NUMBER,
  MONTH_NUMBER_TO_ID,
  reportingPeriodForMonth,
} from "./month";
import type { BudgetItem, MonthlyBudget, Transaction } from "./types";

const SHORT_MONTH_LABELS = [
  "jan",
  "feb",
  "mrt",
  "apr",
  "mei",
  "jun",
  "jul",
  "aug",
  "sep",
  "okt",
  "nov",
  "dec",
] as const;

const MS_PER_DAY = 86_400_000;

export interface ExpectedPayment {
  date: string;
  daysFromToday: number;
  dateLabel: string;
  remainingLabel: string;
}

export function todayIsoDate(now = new Date()): string {
  return toIsoDate(now.getFullYear(), now.getMonth(), now.getDate());
}

export function expectedPaymentForItem(
  item: Pick<BudgetItem, "id" | "type">,
  transactions: Transaction[],
  month: Pick<MonthlyBudget, "monthId" | "year">,
  today = todayIsoDate()
): ExpectedPayment | null {
  const period = reportingPeriodForMonth(month);
  const historyDays = transactions
    .filter((tx) => !tx.isPending && transactionCountsTowardBudgetItem(tx, item))
    .map((tx) => tx.date.slice(0, 10))
    .filter((date) => date < period.start)
    .map((date) => Number.parseInt(date.slice(8, 10), 10))
    .filter((day) => Number.isFinite(day) && day >= 1 && day <= 31);

  if (historyDays.length === 0) {
    return null;
  }

  const date = nextExpectedDate(medianDay(historyDays), month, today);
  const daysFromToday = calendarDaysBetween(today, date);

  return {
    date,
    daysFromToday,
    dateLabel: formatDayMonth(date),
    remainingLabel: formatDaysRemaining(daysFromToday),
  };
}

export function formatDaysRemaining(days: number): string {
  if (days <= 0) {
    return "vandaag";
  }
  if (days === 1) {
    return "over 1 dag";
  }
  return `over ${days} dagen`;
}

function medianDay(days: number[]): number {
  const sorted = [...days].sort((a, b) => a - b);
  const mid = Math.floor((sorted.length - 1) / 2);
  if (sorted.length % 2 === 1) {
    return sorted[mid];
  }
  return Math.round((sorted[mid] + sorted[mid + 1]) / 2);
}

function nextSparenMonth(
  month: Pick<MonthlyBudget, "monthId" | "year">
): { monthId: string; year: number } {
  const number = Number.parseInt(MONTH_ID_TO_NUMBER[month.monthId] ?? "1", 10);
  if (number >= 12) {
    return { monthId: "jan", year: month.year + 1 };
  }

  return {
    monthId: MONTH_NUMBER_TO_ID[number + 1] ?? "jan",
    year: month.year,
  };
}

/** Typische dag in de rapportageperiode, doorgeschoven tot vandaag of later. */
function nextExpectedDate(
  dayOfMonth: number,
  month: Pick<MonthlyBudget, "monthId" | "year">,
  today: string
): string {
  let cursor = { monthId: month.monthId, year: month.year };
  for (let step = 0; step < 14; step++) {
    const date = dateInReportingPeriod(dayOfMonth, reportingPeriodForMonth(cursor));
    if (date >= today) {
      return date;
    }
    cursor = nextSparenMonth(cursor);
  }

  return dateInReportingPeriod(dayOfMonth, reportingPeriodForMonth(cursor));
}

function dateInReportingPeriod(
  dayOfMonth: number,
  period: { start: string; end: string }
): string {
  const start = parseIsoDate(period.start);
  const end = parseIsoDate(period.end);
  if (dayOfMonth >= start.day) {
    return clampDay(start.year, start.monthIndex, dayOfMonth);
  }
  return clampDay(end.year, end.monthIndex, dayOfMonth);
}

function clampDay(year: number, monthIndex: number, day: number): string {
  const lastDay = new Date(year, monthIndex + 1, 0).getDate();
  return toIsoDate(year, monthIndex, Math.min(Math.max(1, day), lastDay));
}

function calendarDaysBetween(fromIso: string, toIso: string): number {
  const from = parseIsoDate(fromIso);
  const to = parseIsoDate(toIso);
  const a = Date.UTC(from.year, from.monthIndex, from.day);
  const b = Date.UTC(to.year, to.monthIndex, to.day);
  return Math.round((b - a) / MS_PER_DAY);
}

function formatDayMonth(iso: string): string {
  const { day, monthIndex } = parseIsoDate(iso);
  return `${day} ${SHORT_MONTH_LABELS[monthIndex]}`;
}

function parseIsoDate(value: string): { year: number; monthIndex: number; day: number } {
  const [year, month, day] = value.slice(0, 10).split("-").map(Number);
  return { year, monthIndex: month - 1, day };
}

function toIsoDate(year: number, monthIndex: number, day: number): string {
  return `${year}-${String(monthIndex + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
}
