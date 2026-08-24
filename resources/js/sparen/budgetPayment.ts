import { isLinkExcludedTransaction } from "./matchRule";
import type { BudgetItem, Transaction } from "./types";

export function expectedTransactionTypeForBudgetItem(
  item: Pick<BudgetItem, "type">
): Transaction["type"] {
  if (item.type === "inkomsten") return "Inkomsten";
  if (item.type === "sparen") return "Sparen";
  return "Uitgave";
}

/** Alleen mutaties met passend type tellen mee op een begrotingspost (geen pot-stortingen op uitgaven). */
export function transactionCountsTowardBudgetItem(
  tx: Pick<Transaction, "budgetItemId" | "type">,
  item: Pick<BudgetItem, "id" | "type">
): boolean {
  if (!tx.budgetItemId || tx.budgetItemId !== item.id) {
    return false;
  }

  return tx.type === expectedTransactionTypeForBudgetItem(item);
}

/** Gekoppeld via post-id, of (voor ongekoppelde rijen) rubriek + omschrijving + passend type. */
export function transactionMatchesBudgetItem(
  tx: Pick<
    Transaction,
    "budgetItemId" | "type" | "categoryGroup" | "description" | "linkExcluded"
  >,
  item: Pick<BudgetItem, "id" | "type" | "group">
): boolean {
  if (isLinkExcludedTransaction(tx)) {
    return false;
  }

  if (transactionCountsTowardBudgetItem(tx, item)) {
    return true;
  }

  if (tx.budgetItemId || tx.categoryGroup !== item.group) {
    return false;
  }

  if (tx.type !== expectedTransactionTypeForBudgetItem(item)) {
    return false;
  }

  const desc = tx.description.toLowerCase();
  const itemNameLower = item.name.toLowerCase();
  return desc.includes(itemNameLower) || itemNameLower.includes(desc);
}
