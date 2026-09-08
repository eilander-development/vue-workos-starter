import type { Transaction, TransactionAllocation } from "./types";

export function normalizeAllocations(
  rows: unknown
): TransactionAllocation[] | undefined {
  if (!Array.isArray(rows)) {
    return undefined;
  }

  const clean: TransactionAllocation[] = [];
  for (const row of rows) {
    if (!row || typeof row !== "object") {
      continue;
    }
    const budgetItemId = String(
      (row as { budgetItemId?: unknown }).budgetItemId ?? ""
    ).trim();
    const amount = Math.abs(Number((row as { amount?: unknown }).amount ?? 0));
    if (!budgetItemId || !Number.isFinite(amount) || amount <= 0) {
      continue;
    }
    clean.push({ budgetItemId, amount: roundMoney(amount) });
  }

  return clean.length >= 2 ? clean : undefined;
}

export function scaleAllocationsToAmount(
  rows: TransactionAllocation[],
  absAmount: number
): TransactionAllocation[] {
  const total = roundMoney(Math.abs(absAmount));
  const clean = normalizeAllocations(rows);
  if (!clean || total <= 0) {
    return [];
  }

  const sum = roundMoney(clean.reduce((acc, row) => acc + row.amount, 0));
  if (sum <= 0) {
    return [];
  }
  if (Math.abs(sum - total) < 0.005) {
    return clean.map((row) => ({ ...row, amount: roundMoney(row.amount) }));
  }

  let remaining = total;
  return clean.map((row, index) => {
    if (index === clean.length - 1) {
      return { budgetItemId: row.budgetItemId, amount: roundMoney(remaining) };
    }
    const amount = roundMoney((row.amount / sum) * total);
    remaining = roundMoney(remaining - amount);
    return { budgetItemId: row.budgetItemId, amount };
  });
}

export function allocationsFingerprint(
  rows: TransactionAllocation[] | undefined | null
): string {
  const clean = normalizeAllocations(rows ?? undefined);
  if (!clean) {
    return "";
  }
  return JSON.stringify(
    clean.map((row) => ({
      budgetItemId: row.budgetItemId,
      amount: roundMoney(row.amount),
    }))
  );
}

export function amountTowardBudgetItem(
  tx: Pick<Transaction, "budgetItemId" | "amount" | "allocations">,
  itemId: string
): number {
  const split = normalizeAllocations(tx.allocations);
  if (split) {
    const hit = split.find((row) => row.budgetItemId === itemId);
    return hit ? hit.amount : 0;
  }

  if (tx.budgetItemId === itemId) {
    return Math.abs(tx.amount);
  }

  return 0;
}

export function transactionAllocatesToItem(
  tx: Pick<Transaction, "budgetItemId" | "allocations">,
  itemId: string
): boolean {
  if (tx.budgetItemId === itemId) {
    return true;
  }
  return Boolean(normalizeAllocations(tx.allocations)?.some((row) => row.budgetItemId === itemId));
}

export function isSplitAllocations(
  rows: TransactionAllocation[] | undefined | null
): boolean {
  return Boolean(normalizeAllocations(rows ?? undefined));
}

export function roundMoney(value: number): number {
  return Math.round(value * 100) / 100;
}
