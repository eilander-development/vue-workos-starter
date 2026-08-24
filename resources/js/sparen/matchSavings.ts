import type { SavingsGoal, Transaction } from "./types";
import { isUnlinkedTransaction } from "./matchRule";

export function compactIban(value?: string | null): string {
  return (value ?? "").replace(/\s+/g, "").toUpperCase();
}

export function isOwnIban(iban: string, ownIbans: string[]): boolean {
  const compact = compactIban(iban);
  return compact.length >= 10 && ownIbans.some((own) => compactIban(own) === compact);
}

/** ING spaaroverboekingen: "Naar Oranje spaarrekening …" vs "Van Oranje spaarrekening …". */
export function savingsTransferDirection(
  tx: Pick<Transaction, "description">
): "naar" | "van" | null {
  const normalized = tx.description.trim().toLowerCase();
  if (/^naar\s/.test(normalized) || /\snaar\s/.test(normalized)) {
    return "naar";
  }
  if (/^van\s/.test(normalized) || /\svan\s/.test(normalized)) {
    return "van";
  }

  return null;
}

/** Storting op spaarrekening/potje (van betaalrekening → spaar). */
export function isSavingsDepositTransaction(
  tx: Pick<Transaction, "description" | "amount">
): boolean {
  const direction = savingsTransferDirection(tx);
  if (direction === "naar") return true;
  if (direction === "van") return false;

  // Op de betaalrekening: negatief bedrag = geld gaat naar spaarrekening.
  return tx.amount < 0;
}

/** Opname van spaarrekening/potje (spaar → betaalrekening). */
export function isSavingsWithdrawalTransaction(
  tx: Pick<Transaction, "description" | "amount">
): boolean {
  const direction = savingsTransferDirection(tx);
  if (direction === "van") return true;
  if (direction === "naar") return false;

  return tx.amount > 0;
}

export function transactionMatchesSavingsGoal(
  tx: Pick<Transaction, "description" | "counterparty">,
  goal: Pick<SavingsGoal, "name" | "accountIban">,
  ownIbans: string[] = []
): boolean {
  const haystack = `${tx.description} ${tx.counterparty ?? ""}`;
  const keyword = goal.name.trim().toLowerCase();
  const descMatch = keyword.length >= 3 && haystack.toLowerCase().includes(keyword);

  const iban = compactIban(goal.accountIban);
  const ibanUsable = iban.length >= 10 && !isOwnIban(iban, ownIbans);
  const ibanMatch = ibanUsable && compactIban(haystack).includes(iban);

  return descMatch || ibanMatch;
}

/** Alleen stortingen (Naar) horen bij een spaarrekening/potje. */
export function transactionMatchesSavingsGoalDeposit(
  tx: Pick<Transaction, "description" | "counterparty" | "amount">,
  goal: Pick<SavingsGoal, "name" | "accountIban">,
  ownIbans: string[] = []
): boolean {
  return (
    transactionMatchesSavingsGoal(tx, goal, ownIbans) && isSavingsDepositTransaction(tx)
  );
}

/** Opnames (Van) van een spaarrekening/potje terug naar betaalrekening. */
export function transactionMatchesSavingsGoalWithdrawal(
  tx: Pick<Transaction, "description" | "counterparty" | "amount">,
  goal: Pick<SavingsGoal, "name" | "accountIban">,
  ownIbans: string[] = []
): boolean {
  return (
    transactionMatchesSavingsGoal(tx, goal, ownIbans) && isSavingsWithdrawalTransaction(tx)
  );
}

/** Saldo-effect: stortingen verhogen, opnames verlagen. */
export function savingsBalanceDelta(
  tx: Pick<Transaction, "description" | "amount">
): number {
  return -tx.amount;
}

export function matchingSavingsTransactions(
  transactions: Transaction[],
  goal: Pick<SavingsGoal, "name" | "accountIban">,
  ownIbans: string[] = []
): Transaction[] {
  return transactions.filter((tx) =>
    transactionMatchesSavingsGoalDeposit(tx, goal, ownIbans)
  );
}

export function matchingUnlinkedSavingsTransactions(
  transactions: Transaction[],
  goal: Pick<SavingsGoal, "name" | "accountIban">,
  ownIbans: string[] = [],
  exceptId?: string
): Transaction[] {
  return matchingSavingsTransactions(transactions, goal, ownIbans).filter(
    (tx) => tx.id !== exceptId && isUnlinkedTransaction(tx)
  );
}
