import type { BudgetItem, MonthlyBudget, SavingsGoal, Transaction } from "./types";

export type PotSettlement = {
  goal: SavingsGoal;
  budgetItem?: BudgetItem;
  budgeted: number;
  spent: number;
  compensated: number;
  toTransfer: number;
  overBudget: number;
  linkedTxCount: number;
};

/** Overboekingen pot → rekening herkennen we lichtgewicht op omschrijving. */
export function isLikelyPotCompensation(tx: Transaction, goal: SavingsGoal): boolean {
  if (tx.amount <= 0) return false;
  const haystack = `${tx.description} ${tx.counterparty ?? ""}`.toLowerCase();
  const keyword = goal.name.trim().toLowerCase();
  if (keyword.length < 3) return false;
  const hints = ["van ", "vanaf ", "pot", "spaar", "oranje", "doelsparen", "compens"];
  const hasHint = hints.some((hint) => haystack.includes(hint));
  return haystack.includes(keyword) && hasHint;
}

export function monthKeyFromDate(date: string): string {
  const month = Number(date.slice(5, 7));
  const ids = ["jan", "feb", "mrt", "apr", "mei", "jun", "jul", "aug", "sep", "okt", "nov", "dec"];
  return ids[month - 1] ?? "";
}

export function computePotSettlement(
  goal: SavingsGoal,
  currentMonth: MonthlyBudget,
  transactions: Transaction[]
): PotSettlement {
  const budgetItem = currentMonth.items.find((item) => item.id === goal.categoryBudgetItemId);
  const budgeted = budgetItem?.actual ?? goal.monthlyContribution ?? 0;
  const spent = budgetItem?.paidOrReceived ?? 0;

  const monthTxs = transactions.filter(
    (tx) => monthKeyFromDate(tx.date) === currentMonth.monthId && !tx.isPending
  );
  const compensated = monthTxs
    .filter((tx) => isLikelyPotCompensation(tx, goal))
    .reduce((sum, tx) => sum + tx.amount, 0);

  const linkedTxCount = monthTxs.filter((tx) => tx.budgetItemId === goal.categoryBudgetItemId).length;
  const toTransfer = Math.max(0, spent - compensated);
  const overBudget = Math.max(0, spent - budgeted);

  return {
    goal,
    budgetItem,
    budgeted,
    spent,
    compensated,
    toTransfer,
    overBudget,
    linkedTxCount,
  };
}

export function isPotGoal(goal: SavingsGoal): boolean {
  return goal.kind === "pot";
}
