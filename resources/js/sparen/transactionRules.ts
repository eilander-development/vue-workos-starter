import type { Rule, SavingsGoal, Transaction, TransactionAllocation } from "./types";
import { isUnlinkedTransaction, transactionMatchesRule } from "./matchRule";
import { savingsRuleIdentifiesGoal, transactionMatchesSavingsGoalDeposit } from "./matchSavings";
import { allocationsFingerprint, normalizeAllocations, scaleAllocationsToAmount } from "./allocations";
import { applyPotTransferLinkExclusion, goalBudgetItemIds, isPotGoal } from "./potSettlement";

export function applyTransactionRules(txs: Transaction[], rls: Rule[], sGoals: SavingsGoal[], ownIbans: string[] = []) {
  return txs.map((tx) => {
    const savingsApplied = applyPotTransferLinkExclusion(tx, sGoals, ownIbans);
    if (savingsApplied.linkExcluded) {
      return savingsApplied;
    }

    const savingsGoal =
      sGoals.find(
        (goal) => !isPotGoal(goal) && transactionMatchesSavingsGoalDeposit(tx, goal, ownIbans)
      ) ?? null;

    const goalBudgetItemId = savingsGoal ? goalBudgetItemIds(savingsGoal)[0] : undefined;
    if (savingsGoal && goalBudgetItemId && isUnlinkedTransaction(tx)) {
      return {
        ...tx,
        type: "Sparen" as const,
        categoryGroup: "Spaargeld" as const,
        budgetItemId: goalBudgetItemId,
        assignedSavingsGoalId: savingsGoal.id,
        matchedRuleId: undefined,
        allocations: undefined,
        linkExcluded: false,
        linkExclusionReason: undefined,
      };
    }

    const candidates = savingsGoal
      ? [...rls].sort((a, b) => b.keyword.trim().length - a.keyword.trim().length)
      : rls;
    const match = candidates.find((rule) => {
      if (!rule.isActive || !transactionMatchesRule(tx, rule)) {
        return false;
      }
      if (savingsGoal && rule.targetType === "sparen" &&
          (!rule.targetBudgetItemId || !savingsRuleIdentifiesGoal(rule.keyword, savingsGoal))) {
        return false;
      }
      return true;
    });
    const splitFromRule = match ? allocationsForRule(match, tx) : undefined;

    if (match && isUnlinkedTransaction(tx)) {
      return {
        ...tx,
        categoryGroup: match.targetGroup,
        type:
          match.targetType === "inkomsten"
            ? ("Inkomsten" as const)
            : match.targetType === "sparen"
              ? ("Sparen" as const)
              : ("Uitgave" as const),
        budgetItemId: match.targetBudgetItemId || tx.budgetItemId,
        matchedRuleId: match.id,
        allocations: splitFromRule,
        assignedSavingsGoalId: savingsGoal?.id ?? tx.assignedSavingsGoalId,
      };
    }

    let next = tx;

    if (match && tx.matchedRuleId === match.id) {
      const nextFingerprint = allocationsFingerprint(splitFromRule);
      const currentFingerprint = allocationsFingerprint(tx.allocations);
      if (nextFingerprint !== currentFingerprint) {
        next = { ...next, allocations: splitFromRule };
      }
    }

    if (savingsGoal && isUnlinkedTransaction(next)) {
      const budgetItemId = goalBudgetItemIds(savingsGoal)[0];
      return {
        ...next,
        type: "Sparen" as const,
        categoryGroup: "Spaargeld" as const,
        budgetItemId: budgetItemId || next.budgetItemId,
        assignedSavingsGoalId: savingsGoal.id,
        linkExcluded: false,
        linkExclusionReason: undefined,
      };
    }

    if (savingsGoal && next.assignedSavingsGoalId !== savingsGoal.id) {
      return {
        ...next,
        assignedSavingsGoalId: savingsGoal.id,
      };
    }

    return next;
  });
}

function allocationsForRule(rule: Rule, tx: Pick<Transaction, "amount">): TransactionAllocation[] | undefined {
  return normalizeAllocations(scaleAllocationsToAmount(rule.allocations ?? [], Math.abs(tx.amount)));
}
