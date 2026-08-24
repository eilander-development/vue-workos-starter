import type { MonthlyBudget, Transaction } from "./types";

export const MONTH_ID_TO_NUMBER: Record<string, string> = {
  jan: "01",
  feb: "02",
  mrt: "03",
  apr: "04",
  mei: "05",
  jun: "06",
  jul: "07",
  aug: "08",
  sep: "09",
  okt: "10",
  nov: "11",
  dec: "12",
};

export const MONTH_NUMBER_TO_ID: Record<number, string> = {
  1: "jan",
  2: "feb",
  3: "mrt",
  4: "apr",
  5: "mei",
  6: "jun",
  7: "jul",
  8: "aug",
  9: "sep",
  10: "okt",
  11: "nov",
  12: "dec",
};

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

export interface ReportingPeriodRange {
  start: string;
  end: string;
}

export interface ReportingSettings {
  startDayOfMonth: number;
  defaultMonthId: string;
  defaultYear: number;
}

let reportingStartDay = 15;

export function setReportingStartDay(day: number): void {
  reportingStartDay = Math.max(1, Math.min(28, day));
}

export function getReportingStartDay(): number {
  return reportingStartDay;
}

function pad(value: number): string {
  return value.toString().padStart(2, "0");
}

function toIsoDate(year: number, monthIndex: number, day: number): string {
  return `${year}-${pad(monthIndex + 1)}-${pad(day)}`;
}

function parseIsoDate(value: string): { year: number; monthIndex: number; day: number } {
  const [year, month, day] = value.slice(0, 10).split("-").map(Number);
  return { year, monthIndex: month - 1, day };
}

/**
 * Sparen months are labeled by their start month (e.g. augustus = 15 aug – 14 sep).
 */
export function reportingPeriodForMonth(
  month: Pick<MonthlyBudget, "monthId" | "year">
): ReportingPeriodRange {
  const monthNumber = Number.parseInt(MONTH_ID_TO_NUMBER[month.monthId] ?? "1", 10);
  const start = new Date(month.year, monthNumber - 1, reportingStartDay);
  const end = new Date(month.year, monthNumber, reportingStartDay - 1);

  return {
    start: toIsoDate(start.getFullYear(), start.getMonth(), start.getDate()),
    end: toIsoDate(end.getFullYear(), end.getMonth(), end.getDate()),
  };
}

export function reportingPeriodForMonthId(
  monthId: string,
  year: number
): ReportingPeriodRange {
  return reportingPeriodForMonth({ monthId, year });
}

export function isDateInReportingPeriod(date: string, period: ReportingPeriodRange): boolean {
  const normalized = date.slice(0, 10);
  return normalized >= period.start && normalized <= period.end;
}

export function isTransactionInReportingMonth(
  tx: Pick<Transaction, "date">,
  month: Pick<MonthlyBudget, "monthId" | "year">
): boolean {
  return isDateInReportingPeriod(tx.date, reportingPeriodForMonth(month));
}

export function defaultReportingMonth(now = new Date()): { monthId: string; year: number } {
  let monthIndex = now.getMonth();
  let year = now.getFullYear();

  if (now.getDate() < reportingStartDay) {
    monthIndex -= 1;
    if (monthIndex < 0) {
      monthIndex = 11;
      year -= 1;
    }
  }

  return {
    monthId: MONTH_NUMBER_TO_ID[monthIndex + 1] ?? "jan",
    year,
  };
}

export function formatReportingPeriodLabel(period: ReportingPeriodRange): string {
  const start = parseIsoDate(period.start);
  const end = parseIsoDate(period.end);
  const sameYear = start.year === end.year;

  const startLabel = `${start.day} ${SHORT_MONTH_LABELS[start.monthIndex]}`;
  const endLabel = `${end.day} ${SHORT_MONTH_LABELS[end.monthIndex]}`;

  if (sameYear) {
    return `${startLabel} – ${endLabel} ${start.year}`;
  }

  return `${startLabel} ${start.year} – ${endLabel} ${end.year}`;
}

export function formatReportingPeriodShort(period: ReportingPeriodRange): string {
  const start = parseIsoDate(period.start);
  const end = parseIsoDate(period.end);

  return `${start.day} ${SHORT_MONTH_LABELS[start.monthIndex]} – ${end.day} ${SHORT_MONTH_LABELS[end.monthIndex]}`;
}

/** @deprecated Use isTransactionInReportingMonth instead. */
export function monthDatePrefix(month: Pick<MonthlyBudget, "monthId" | "year">): string {
  return reportingPeriodForMonth(month).start.slice(0, 7);
}

export function applyReportingSettings(settings?: Partial<ReportingSettings> | null): void {
  if (!settings?.startDayOfMonth) {
    return;
  }

  setReportingStartDay(settings.startDayOfMonth);
}
