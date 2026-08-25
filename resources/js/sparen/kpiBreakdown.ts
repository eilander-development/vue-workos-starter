import type { BudgetItem } from "../types";
import type { KpiBreakdownColumn, KpiBreakdownRow } from "../components/KpiBreakdownModal.vue";

/** Post heeft een begroting in deze maand. */
export function hasBudget(item: BudgetItem): boolean {
  return item.actual > 0;
}

/** Post gebruikt regels per maand (losse regels) als begroting. */
export function hasMonthEntries(item: BudgetItem): boolean {
  return (item.monthEntries?.length ?? 0) > 0;
}

export function isFixedBudgetItem(item: BudgetItem): boolean {
  return hasBudget(item) && !hasMonthEntries(item);
}

export function isMonthEntryBudgetItem(item: BudgetItem): boolean {
  return hasBudget(item) && hasMonthEntries(item);
}

export function sumFixedBudgetedAmount(items: BudgetItem[]): number {
  return items.reduce((sum, item) => sum + (isFixedBudgetItem(item) ? item.actual : 0), 0);
}

export function sumMonthEntryBudgetedAmount(items: BudgetItem[]): number {
  return items.reduce((sum, item) => sum + (isMonthEntryBudgetItem(item) ? item.actual : 0), 0);
}

export function sumFixedBudgetedPaid(items: BudgetItem[]): number {
  return items.reduce(
    (sum, item) => sum + (isFixedBudgetItem(item) ? withinBudgetPaid(item) : 0),
    0
  );
}

export function sumMonthEntryBudgetedPaid(items: BudgetItem[]): number {
  return items.reduce(
    (sum, item) => sum + (isMonthEntryBudgetItem(item) ? withinBudgetPaid(item) : 0),
    0
  );
}

export function sumFixedBudgetedRemaining(items: BudgetItem[]): number {
  return items.reduce(
    (sum, item) =>
      sum + (isFixedBudgetItem(item) ? Math.max(0, item.actual - item.paidOrReceived) : 0),
    0
  );
}

export function sumMonthEntryBudgetedRemaining(items: BudgetItem[]): number {
  return items.reduce(
    (sum, item) =>
      sum + (isMonthEntryBudgetItem(item) ? Math.max(0, item.actual - item.paidOrReceived) : 0),
    0
  );
}

export function sumBudgetedAmount(items: BudgetItem[]): number {
  return items.reduce((sum, item) => sum + item.actual, 0);
}

/** Deel van de bankmutatie dat binnen het budget valt. */
export function withinBudgetPaid(item: BudgetItem): number {
  if (!hasBudget(item)) {
    return 0;
  }
  return Math.min(item.paidOrReceived, item.actual);
}

/** Overschot boven budget, of het hele bedrag bij een onbegrote post. */
export function budgetOverspend(item: BudgetItem): number {
  if (hasBudget(item)) {
    return Math.max(0, item.paidOrReceived - item.actual);
  }
  return Math.max(0, item.paidOrReceived);
}

/** Reeds bijgeschreven / afgeschreven: tot aan het budget, geen overschot. */
export function sumBudgetedPaid(items: BudgetItem[]): number {
  return items.reduce((sum, item) => sum + withinBudgetPaid(item), 0);
}

export function sumBudgetedRemaining(items: BudgetItem[]): number {
  return items.reduce(
    (sum, item) =>
      sum + (hasBudget(item) ? Math.max(0, item.actual - item.paidOrReceived) : 0),
    0
  );
}

/** Meevallers / overschrijding: boven begroting + volledige onbegrote ontvangsten. */
export function sumBudgetedOver(items: BudgetItem[]): number {
  return items.reduce((sum, item) => {
    if (hasBudget(item)) {
      return sum + Math.max(0, item.paidOrReceived - item.actual);
    }
    return sum + Math.max(0, item.paidOrReceived);
  }, 0);
}

/** Alle bankbedragen (o.a. voor werkelijk netto / cashflow). */
export function sumAllPaid(items: BudgetItem[]): number {
  return items.reduce((sum, item) => sum + item.paidOrReceived, 0);
}

/** Verschil bank vs begroting (positief = meer ontvangen/betaald dan begroot). */
export function sumBudgetDelta(items: BudgetItem[]): number {
  return sumAllPaid(items) - sumBudgetedAmount(items);
}

const euroCols = (keys: string[]): KpiBreakdownColumn[] =>
  keys.map((key, index) => ({
    key,
    label:
      key === "name"
        ? "Post"
        : key === "group"
          ? "Rubriek"
          : key === "budget"
            ? "Begroot"
            : key === "paid"
              ? "Bank"
              : key === "remaining"
                ? "Rest"
                : key === "over"
                  ? "Overschrijding"
                  : key === "amount"
                    ? "Bedrag"
                    : key,
    align: key === "name" || key === "group" || key === "label" ? "left" : "right",
    emphasize: index === 0,
  }));

export function budgetItemRows(
  items: BudgetItem[],
  metric: "budget" | "paid" | "paidAll" | "remaining" | "over",
  onOpenItem?: (item: BudgetItem) => void
): { columns: KpiBreakdownColumn[]; rows: KpiBreakdownRow[]; total: number } {
  const columns: KpiBreakdownColumn[] = [
    { key: "name", label: "Post", emphasize: true },
    { key: "group", label: "Rubriek" },
    { key: "budget", label: "Begroot", align: "right" },
    { key: "paid", label: "Bank", align: "right" },
  ];

  if (metric === "remaining") {
    columns.push({ key: "remaining", label: "Nog open", align: "right", emphasize: true });
  }
  if (metric === "over") {
    columns.push({
      key: "over",
      label: "Overschrijding",
      align: "right",
      emphasize: true,
      colorClass: () => "text-rose-400 font-semibold",
    });
  }

  const filtered = items.filter((item) => {
    if (metric === "budget") return true;
    if (metric === "paidAll") return item.paidOrReceived > 0;
    if (metric === "paid") return hasBudget(item) && item.paidOrReceived > 0;
    if (metric === "remaining") return hasBudget(item) && item.actual - item.paidOrReceived > 0;
    if (hasBudget(item)) return item.paidOrReceived > item.actual;
    return item.paidOrReceived > 0;
  });

  const rows: KpiBreakdownRow[] = filtered.map((item) => {
    const remaining = Math.max(0, item.actual - item.paidOrReceived);
    const over = budgetOverspend(item);
    return {
      id: item.id,
      cells: {
        name: item.name,
        group: item.group,
        budget: item.actual,
        paid: metric === "paidAll" ? item.paidOrReceived : withinBudgetPaid(item),
        remaining,
        over,
      },
      onClick: onOpenItem ? () => onOpenItem(item) : undefined,
    };
  });

  const total = filtered.reduce((sum, item) => {
    if (metric === "budget") return sum + item.actual;
    if (metric === "paid") return sum + withinBudgetPaid(item);
    if (metric === "paidAll") return sum + item.paidOrReceived;
    if (metric === "remaining") return sum + Math.max(0, item.actual - item.paidOrReceived);
    if (hasBudget(item)) return sum + Math.max(0, item.paidOrReceived - item.actual);
    return sum + Math.max(0, item.paidOrReceived);
  }, 0);

  return { columns, rows, total };
}

export type FormulaLine = {
  id: string;
  label: string;
  amount?: number;
  tone?: "plus" | "minus" | "result" | "subresult" | "section" | "neutral";
};

function formulaAmountColor(tone: string, value: number | string): string | undefined {
  if (tone === "section") return undefined;
  if (tone === "result") {
    return Number(value) >= 0 ? "text-indigo-300 font-bold" : "text-rose-400 font-bold";
  }
  if (tone === "subresult") {
    return Number(value) >= 0 ? "text-indigo-300 font-semibold" : "text-rose-400 font-semibold";
  }
  if (tone === "minus") return "text-rose-400";
  if (tone === "plus") return "text-emerald-400";
  return "text-slate-300";
}

export function formulaRows(
  lines: FormulaLine[]
): { columns: KpiBreakdownColumn[]; rows: KpiBreakdownRow[]; total: number } {
  const columns: KpiBreakdownColumn[] = [
    { key: "label", label: "Onderdeel", emphasize: true },
    {
      key: "amount",
      label: "Bedrag",
      align: "right",
      colorClass: (value, row) => formulaAmountColor(String(row.cells.tone), value),
    },
  ];

  const rows: KpiBreakdownRow[] = lines.map((line) => ({
    id: line.id,
    cells: {
      label: line.label,
      amount: line.tone === "section" ? "" : (line.amount ?? 0),
      tone: line.tone ?? "neutral",
    },
  }));

  const total = lines.find((line) => line.tone === "result")?.amount ?? lines.at(-1)?.amount ?? 0;
  return { columns, rows, total };
}

export { euroCols };
