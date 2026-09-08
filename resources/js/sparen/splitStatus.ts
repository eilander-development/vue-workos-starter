import {
  amountTowardBudgetItem,
  isSplitAllocations,
  normalizeAllocations,
  roundMoney,
} from "./allocations";
import { isTransactionInReportingMonth } from "./month";
import { transactionsMatchingRule } from "./matchRule";
import type { BudgetItem, MonthlyBudget, Rule, Transaction } from "./types";

export const SPLIT_BALANCE_EPS = 0.009;

export type SplitVarianceKind = "ok" | "short" | "over";

export interface SplitVariance {
  kind: SplitVarianceKind;
  amount: number;
  label: string;
}

export interface SplitEnvelopeStatus {
  budgetItemId: string;
  name: string;
  group: string;
  planned: number;
  filled: number;
  difference: number;
  variance: SplitVariance;
}

export interface SplitTransactionStatus {
  tx: Transaction;
  amount: number;
  allocated: number;
  remainder: number;
  variance: SplitVariance;
}

export interface SplitReview {
  rule: Rule;
  plannedTotal: number;
  bankTotal: number;
  filledTotal: number;
  bankDifference: number;
  bankVariance: SplitVariance;
  envelopes: SplitEnvelopeStatus[];
  transactions: SplitTransactionStatus[];
  hasIssue: boolean;
}

function euro(amount: number): string {
  return Math.abs(amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 });
}

export function splitVariance(difference: number): SplitVariance {
  const amount = roundMoney(difference);
  if (Math.abs(amount) <= SPLIT_BALANCE_EPS) {
    return { kind: "ok", amount: 0, label: "In balans" };
  }
  if (amount < 0) {
    return { kind: "short", amount, label: `€ ${euro(amount)} te kort` };
  }
  return { kind: "over", amount, label: `€ ${euro(amount)} over` };
}

export function isSplitRule(rule: Pick<Rule, "allocations">): boolean {
  return isSplitAllocations(rule.allocations);
}

export function splitRules(rules: Rule[]): Rule[] {
  return rules.filter((rule) => isSplitRule(rule));
}

export function reviewSplit(
  rule: Rule,
  budgetItems: BudgetItem[],
  transactions: Transaction[],
  month: MonthlyBudget
): SplitReview {
  const template = normalizeAllocations(rule.allocations) ?? [];
  const plannedTotal = roundMoney(template.reduce((sum, row) => sum + row.amount, 0));
  const itemById = new Map(budgetItems.map((item) => [item.id, item]));

  const periodTxs = transactionsMatchingRule(transactions, rule).filter((tx) =>
    isTransactionInReportingMonth(tx, month)
  );

  const envelopes: SplitEnvelopeStatus[] = template.map((row) => {
    const item = itemById.get(row.budgetItemId);
    const filled = roundMoney(
      periodTxs.reduce((sum, tx) => sum + amountTowardBudgetItem(tx, row.budgetItemId), 0)
    );
    const difference = roundMoney(filled - row.amount);
    return {
      budgetItemId: row.budgetItemId,
      name: item?.name ?? row.budgetItemId,
      group: item?.group ?? "",
      planned: row.amount,
      filled,
      difference,
      variance: splitVariance(difference),
    };
  });

  const transactionsStatus: SplitTransactionStatus[] = periodTxs.map((tx) => {
    const amount = roundMoney(Math.abs(tx.amount));
    const allocated = roundMoney(
      (normalizeAllocations(tx.allocations) ?? template).reduce((sum, row) => {
        const toward = amountTowardBudgetItem(tx, row.budgetItemId);
        return sum + toward;
      }, 0)
    );
    const remainder = roundMoney(amount - allocated);
    return {
      tx,
      amount,
      allocated,
      remainder,
      variance: splitVariance(remainder),
    };
  });

  const bankTotal = roundMoney(periodTxs.reduce((sum, tx) => sum + Math.abs(tx.amount), 0));
  const filledTotal = roundMoney(envelopes.reduce((sum, row) => sum + row.filled, 0));
  const bankDifference = roundMoney(bankTotal - plannedTotal);
  const bankVariance =
    periodTxs.length === 0
      ? splitVariance(-plannedTotal)
      : splitVariance(bankDifference);

  return {
    rule,
    plannedTotal,
    bankTotal,
    filledTotal,
    bankDifference: periodTxs.length === 0 ? roundMoney(-plannedTotal) : bankDifference,
    bankVariance:
      periodTxs.length === 0
        ? { kind: "short", amount: roundMoney(-plannedTotal), label: "Geen mutatie deze periode" }
        : bankVariance,
    envelopes,
    transactions: transactionsStatus,
    hasIssue:
      (periodTxs.length === 0 && plannedTotal > 0) ||
      bankVariance.kind !== "ok" ||
      envelopes.some((row) => row.variance.kind !== "ok") ||
      transactionsStatus.some((row) => row.variance.kind !== "ok"),
  };
}

export function reviewSplits(
  rules: Rule[],
  budgetItems: BudgetItem[],
  transactions: Transaction[],
  month: MonthlyBudget
): SplitReview[] {
  return splitRules(rules).map((rule) => reviewSplit(rule, budgetItems, transactions, month));
}
