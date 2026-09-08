import { transactionAllocatesToItem } from "./allocations";
import { isLinkExcludedTransaction } from "./matchRule";
import type { BudgetItem, Transaction } from "./types";

export function expectedTransactionTypeForBudgetItem(
  item: Pick<BudgetItem, "type">
): Transaction["type"] {
  if (item.type === "inkomsten") return "Inkomsten";
  if (item.type === "sparen") return "Sparen";
  return "Uitgave";
}

/** Gekoppelde mutatie telt mee op de post, ook als het een spaaroverboeking is. */
export function transactionCountsTowardBudgetItem(
  tx: Pick<Transaction, "budgetItemId" | "type" | "allocations">,
  item: Pick<BudgetItem, "id" | "type">
): boolean {
  if (!transactionAllocatesToItem(tx, item.id)) {
    return false;
  }

  if (tx.type === expectedTransactionTypeForBudgetItem(item)) {
    return true;
  }

  return item.type === "uitgaven" && tx.type === "Sparen";
}

/** Gekoppeld via post-id, of (voor ongekoppelde rijen) rubriek + omschrijving + passend type. */
export function transactionMatchesBudgetItem(
  tx: Pick<
    Transaction,
    "budgetItemId" | "type" | "categoryGroup" | "description" | "linkExcluded" | "allocations"
  >,
  item: Pick<BudgetItem, "id" | "type" | "group" | "name">
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
