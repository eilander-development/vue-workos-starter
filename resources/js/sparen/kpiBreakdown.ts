import type { BudgetItem } from "../types";
import type { KpiBreakdownColumn, KpiBreakdownRow } from "../components/KpiBreakdownModal";

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
  metric: "budget" | "paid" | "remaining" | "over",
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
    if (metric === "paid") return item.paidOrReceived > 0;
    if (metric === "remaining") return Math.max(0, item.actual - item.paidOrReceived) > 0;
    return item.paidOrReceived > item.actual;
  });

  const rows: KpiBreakdownRow[] = filtered.map((item) => {
    const remaining = Math.max(0, item.actual - item.paidOrReceived);
    const over = Math.max(0, item.paidOrReceived - item.actual);
    return {
      id: item.id,
      cells: {
        name: item.name,
        group: item.group,
        budget: item.actual,
        paid: item.paidOrReceived,
        remaining,
        over,
      },
      onClick: onOpenItem ? () => onOpenItem(item) : undefined,
    };
  });

  const total = filtered.reduce((sum, item) => {
    if (metric === "budget") return sum + item.actual;
    if (metric === "paid") return sum + item.paidOrReceived;
    if (metric === "remaining") return sum + Math.max(0, item.actual - item.paidOrReceived);
    return sum + Math.max(0, item.paidOrReceived - item.actual);
  }, 0);

  return { columns, rows, total };
}

export function formulaRows(
  lines: { id: string; label: string; amount: number; tone?: "plus" | "minus" | "result" }[]
): { columns: KpiBreakdownColumn[]; rows: KpiBreakdownRow[]; total: number } {
  const columns: KpiBreakdownColumn[] = [
    { key: "label", label: "Onderdeel", emphasize: true },
    {
      key: "amount",
      label: "Bedrag",
      align: "right",
      colorClass: (value, row) => {
        const tone = String(row.cells.tone);
        if (tone === "result") return "text-indigo-300 font-bold";
        if (tone === "minus") return "text-rose-400";
        if (tone === "plus") return "text-emerald-400";
        return undefined;
      },
    },
  ];

  const rows: KpiBreakdownRow[] = lines.map((line) => ({
    id: line.id,
    cells: {
      label: line.label,
      amount: line.amount,
      tone: line.tone ?? "plus",
    },
  }));

  const total = lines.find((line) => line.tone === "result")?.amount ?? lines.at(-1)?.amount ?? 0;
  return { columns, rows, total };
}

export { euroCols };
