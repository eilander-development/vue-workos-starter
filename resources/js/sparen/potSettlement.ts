import type { BudgetItem, MonthlyBudget, SavingsGoal, Transaction } from "./types";
import { isTransactionInReportingMonth } from "./month";
import {
  isSavingsWithdrawalTransaction,
  savingsBalanceDelta,
  transactionMatchesSavingsGoal,
  transactionMatchesSavingsGoalDeposit,
  transactionMatchesSavingsGoalWithdrawal,
} from "./matchSavings";

export const POT_DEPOSIT_LINK_EXCLUSION_PREFIX = "Pot-storting";
export const POT_WITHDRAWAL_LINK_EXCLUSION_PREFIX = "Pot-opname";

function potTransferLinkExclusionForGoal(
  goal: SavingsGoal,
  direction: "naar" | "van"
): {
  linkExcluded: true;
  linkExclusionReason: string;
} {
  const prefix =
    direction === "naar"
      ? POT_DEPOSIT_LINK_EXCLUSION_PREFIX
      : POT_WITHDRAWAL_LINK_EXCLUSION_PREFIX;
  const detail =
    direction === "naar"
      ? "apart bijgehouden in potje, niet koppelen aan rubriek"
      : "verrekening in potje, niet koppelen aan rubriek";

  return {
    linkExcluded: true,
    linkExclusionReason: `${prefix} (${goal.name}) — ${detail}`,
  };
}

/** @deprecated Use applyPotTransferLinkExclusion */
export function potDepositLinkExclusionForGoal(goal: SavingsGoal): {
  linkExcluded: true;
  linkExclusionReason: string;
} {
  return potTransferLinkExclusionForGoal(goal, "naar");
}

/** Automatische koppel-uitsluiting voor Naar/Van-overboekingen naar/van een potje. */
export function applyPotTransferLinkExclusion(
  tx: Transaction,
  goals: SavingsGoal[],
  ownIbans: string[] = []
): Transaction {
  for (const goal of goals) {
    if (!isPotGoal(goal)) {
      continue;
    }

    const isDeposit = transactionMatchesSavingsGoalDeposit(tx, goal, ownIbans);
    const isWithdrawal = transactionMatchesSavingsGoalWithdrawal(tx, goal, ownIbans);
    if (!isDeposit && !isWithdrawal) {
      continue;
    }

    const exclusion = potTransferLinkExclusionForGoal(goal, isDeposit ? "naar" : "van");

    return {
      ...tx,
      type: "Sparen",
      categoryGroup: "Spaargeld",
      budgetItemId: undefined,
      matchedRuleId: undefined,
      ...exclusion,
    };
  }

  return tx;
}

/** @deprecated Use applyPotTransferLinkExclusion */
export function applyPotDepositLinkExclusion(
  tx: Transaction,
  goals: SavingsGoal[],
  ownIbans: string[] = []
): Transaction {
  return applyPotTransferLinkExclusion(tx, goals, ownIbans);
}

export type PotSettlement = {
  goal: SavingsGoal;
  budgetItems: BudgetItem[];
  /** @deprecated first linked item; prefer budgetItems */
  budgetItem?: BudgetItem;
  budgeted: number;
  spent: number;
  compensated: number;
  toTransfer: number;
  overBudget: number;
  linkedTxCount: number;
  spentTransactions: Transaction[];
  compensationTransactions: Transaction[];
};

export function isPotGoal(goal: Pick<SavingsGoal, "kind">): boolean {
  return goal.kind === "pot";
}

/** Alle gekoppelde begrotingspost-ids van een spaardoel/potje. */
export function goalBudgetItemIds(
  goal: Pick<SavingsGoal, "categoryBudgetItemId" | "categoryBudgetItemIds">
): string[] {
  const fromList = (goal.categoryBudgetItemIds ?? []).filter(Boolean);
  if (fromList.length > 0) {
    return [...new Set(fromList)];
  }
  return goal.categoryBudgetItemId ? [goal.categoryBudgetItemId] : [];
}

/** Uitgavenrubrieken die via een potje als envelop worden afgerekend. */
export function potLinkedBudgetItemIds(goals: SavingsGoal[]): Set<string> {
  const ids = new Set<string>();
  for (const goal of goals) {
    if (!isPotGoal(goal)) {
      continue;
    }
    for (const id of goalBudgetItemIds(goal)) {
      ids.add(id);
    }
  }
  return ids;
}

export function hasPotEnvelope(item: Pick<BudgetItem, "shadowSpent">): boolean {
  return item.shadowSpent != null;
}

export function shadowOverspend(item: Pick<BudgetItem, "actual" | "shadowSpent">): number {
  if (item.shadowSpent == null) {
    return 0;
  }
  return Math.max(0, item.shadowSpent - (item.actual ?? 0));
}

/** Overboekingen pot → rekening: "Van Oranje spaarrekening …" op de betaalrekening. */
export function isLikelyPotCompensation(tx: Transaction, goal: SavingsGoal): boolean {
  if (tx.amount <= 0) return false;

  const haystack = `${tx.description} ${tx.counterparty ?? ""}`.toLowerCase();
  const keyword = goal.name.trim().toLowerCase();
  if (keyword.length < 3) return false;

  const isVanTransfer =
    /^van\s/.test(tx.description.trim().toLowerCase()) ||
    haystack.includes(" van oranje") ||
    haystack.includes("van oranje spaar");

  if (isVanTransfer && haystack.includes(keyword)) {
    return true;
  }

  const hints = ["vanaf ", "pot", "spaar", "oranje", "doelsparen", "compens"];
  const hasHint = hints.some((hint) => haystack.includes(hint));
  return haystack.includes(keyword) && hasHint;
}

export function computePotSettlement(
  goal: SavingsGoal,
  currentMonth: MonthlyBudget,
  transactions: Transaction[]
): PotSettlement {
  const linkedIds = new Set(goalBudgetItemIds(goal));
  const budgetItems = currentMonth.items.filter((item) => linkedIds.has(item.id));
  const budgeted =
    budgetItems.length > 0
      ? budgetItems.reduce((sum, item) => sum + (item.actual ?? 0), 0)
      : goal.monthlyContribution ?? 0;

  const monthTxs = transactions.filter(
    (tx) => isTransactionInReportingMonth(tx, currentMonth) && !tx.isPending
  );

  // Alleen echte uitgaven op de gekoppelde rubriek(en), geen spaarstortingen.
  const spentTransactions = monthTxs.filter(
    (tx) =>
      !!tx.budgetItemId &&
      linkedIds.has(tx.budgetItemId) &&
      tx.type === "Uitgave" &&
      !isLikelyPotCompensation(tx, goal)
  );
  const spent = spentTransactions.reduce((sum, tx) => sum + Math.abs(tx.amount), 0);

  const compensationTransactions = monthTxs.filter((tx) =>
    isLikelyPotCompensation(tx, goal)
  );
  const compensated = compensationTransactions.reduce((sum, tx) => sum + tx.amount, 0);

  const linkedTxCount = spentTransactions.length;
  const toTransfer = Math.max(0, spent - compensated);
  const overBudget = Math.max(0, spent - budgeted);

  return {
    goal,
    budgetItems,
    budgetItem: budgetItems[0],
    budgeted,
    spent,
    compensated,
    toTransfer,
    overBudget,
    linkedTxCount,
    spentTransactions,
    compensationTransactions,
  };
}

export function potCompensationStatus(
  settlement: Pick<PotSettlement, "spent" | "compensated">
): { sufficient: boolean; shortfall: number; surplus: number } {
  const shortfall = Math.max(0, settlement.spent - settlement.compensated);
  const sufficient = settlement.spent === 0 || shortfall === 0;

  return {
    sufficient,
    shortfall,
    surplus: Math.max(0, settlement.compensated - settlement.spent),
  };
}

export type PotPeriodBalance = {
  currentBalance: number;
  totalDeposits: number;
  totalWithdrawals: number;
  depositTransactions: Transaction[];
  withdrawalTransactions: Transaction[];
};

/** Potjesaldo voor de rapportagemaand (15e t/m 15e), niet cumulatief. */
export function computePotPeriodBalance(
  goal: SavingsGoal,
  currentMonth: MonthlyBudget,
  transactions: Transaction[],
  ownIbans: string[] = []
): PotPeriodBalance {
  const monthTxs = transactions.filter(
    (tx) =>
      isTransactionInReportingMonth(tx, currentMonth) &&
      !tx.isPending &&
      (tx.type === "Sparen" || tx.categoryGroup === "Spaargeld") &&
      transactionMatchesSavingsGoal(tx, goal, ownIbans)
  );

  const depositTransactions = monthTxs.filter((tx) =>
    transactionMatchesSavingsGoalDeposit(tx, goal, ownIbans)
  );
  const withdrawalTransactions = monthTxs.filter(
    (tx) =>
      isSavingsWithdrawalTransaction(tx) &&
      transactionMatchesSavingsGoal(tx, goal, ownIbans)
  );

  const totalDeposits = depositTransactions.reduce(
    (sum, tx) => sum + savingsBalanceDelta(tx),
    0
  );
  const totalWithdrawals = withdrawalTransactions.reduce(
    (sum, tx) => sum + Math.abs(savingsBalanceDelta(tx)),
    0
  );

  return {
    currentBalance: totalDeposits - totalWithdrawals,
    totalDeposits,
    totalWithdrawals,
    depositTransactions,
    withdrawalTransactions,
  };
}
