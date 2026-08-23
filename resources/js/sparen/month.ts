import { MonthlyBudget } from "./types";

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

export function monthDatePrefix(month: Pick<MonthlyBudget, "monthId" | "year">): string {
  const number = MONTH_ID_TO_NUMBER[month.monthId];
  if (!number) {
    return "";
  }

  return `${month.year}-${number}`;
}
