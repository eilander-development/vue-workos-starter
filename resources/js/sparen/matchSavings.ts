import type { SavingsGoal, Transaction } from "./types";
import { isUnlinkedTransaction } from "./matchRule";

export function compactIban(value?: string | null): string {
  return (value ?? "").replace(/\s+/g, "").toUpperCase();
}

export function isOwnIban(iban: string, ownIbans: string[]): boolean {
  const compact = compactIban(iban);
  return compact.length >= 10 && ownIbans.some((own) => compactIban(own) === compact);
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

export function matchingSavingsTransactions(
  transactions: Transaction[],
  goal: Pick<SavingsGoal, "name" | "accountIban">,
  ownIbans: string[] = []
): Transaction[] {
  return transactions.filter((tx) => transactionMatchesSavingsGoal(tx, goal, ownIbans));
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
