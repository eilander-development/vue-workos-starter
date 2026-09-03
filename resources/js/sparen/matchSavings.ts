import type { SavingsGoal, Transaction } from "./types";
import { isUnlinkedTransaction } from "./matchRule";

export function compactIban(value?: string | null): string {
  return (value ?? "").replace(/\s+/g, "").toUpperCase();
}

export function isOwnIban(iban: string, ownIbans: string[]): boolean {
  const compact = compactIban(iban);
  return compact.length >= 10 && ownIbans.some((own) => compactIban(own) === compact);
}

const SAVINGS_REF_PATTERN = /[A-Za-z]\d{5,}/;

/**
 * ING-oranje: gewone spaarrekening heeft een spatie voor het nummer,
 * spaarpotjes plakken het nummer vast aan "spaarrekening".
 *
 * Naar Oranje spaarrekening L13628386 → rekening (koppelbaar)
 * Naar Oranje spaarrekeningC13134173  → potje (niet koppelen)
 */
export function parseIngSavingsDestination(
  text: string
): { ref: string; isSpaarpot: boolean } | null {
  const glued = /spaarrekening([A-Za-z]\d{5,})/i.exec(text);
  if (glued) {
    return { ref: glued[1].toUpperCase(), isSpaarpot: true };
  }

  const spaced = /spaarrekening\s+([A-Za-z]\d{5,})/i.exec(text);
  if (spaced) {
    return { ref: spaced[1].toUpperCase(), isSpaarpot: false };
  }

  return null;
}

export function extractSavingsTransferRef(text: string): string | null {
  const parsed = parseIngSavingsDestination(text);
  if (parsed) {
    return parsed.ref;
  }

  const match = text.match(SAVINGS_REF_PATTERN);
  return match ? match[0].toUpperCase() : null;
}

export function isIngSpaarpotTransfer(tx: Pick<Transaction, "description">): boolean {
  const direction = savingsTransferDirection(tx);
  if (direction !== "naar" && direction !== "van") {
    return false;
  }

  return parseIngSavingsDestination(tx.description)?.isSpaarpot === true;
}

/** Overboeking tussen betaalrekening en spaar/pot, geen echte inkomst of uitgave. */
export function isSavingsCashflowTransfer(
  tx: Pick<Transaction, "type" | "description" | "categoryGroup" | "isPending">
): boolean {
  if (tx.isPending) {
    return false;
  }
  if (tx.type === "Sparen") {
    return true;
  }

  const direction = savingsTransferDirection(tx);
  if (!direction) {
    return false;
  }

  return parseIngSavingsDestination(tx.description) !== null || tx.categoryGroup === "Spaargeld";
}

function isGenericSavingsLabel(name: string): boolean {
  const normalized = name.trim().toLowerCase().replace(/\s+/g, " ");
  return (
    normalized === "spaarrekening" ||
    normalized === "oranje spaarrekening" ||
    normalized === "naar oranje spaarrekening" ||
    normalized === "van oranje spaarrekening"
  );
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

/** Opname van spaarrekening: staat al bij het spaardoel, niet aan een rubriek koppelen. */
export function isSavingsWithdrawalExcludedFromBudget(
  tx: Pick<Transaction, "description" | "amount">
): boolean {
  return (
    savingsTransferDirection(tx) === "van" && parseIngSavingsDestination(tx.description) !== null
  );
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
  tx: Pick<Transaction, "description" | "counterparty" | "assignedSavingsGoalId">,
  goal: Pick<SavingsGoal, "id" | "name" | "accountIban">,
  ownIbans: string[] = []
): boolean {
  if (tx.assignedSavingsGoalId) {
    return tx.assignedSavingsGoalId === goal.id;
  }

  const haystack = `${tx.description} ${tx.counterparty ?? ""}`;
  const txRef = extractSavingsTransferRef(haystack);
  const goalRef = extractSavingsTransferRef(`${goal.name} ${goal.accountIban ?? ""}`);

  if (txRef && goalRef) {
    return txRef === goalRef;
  }

  const iban = compactIban(goal.accountIban);
  const ibanUsable = iban.length >= 10 && !isOwnIban(iban, ownIbans);
  const ibanMatch = ibanUsable && compactIban(haystack).includes(iban);

  if (txRef && !goalRef && isGenericSavingsLabel(goal.name)) {
    return ibanMatch;
  }

  const keyword = goal.name.trim().toLowerCase();
  const descMatch = keyword.length >= 3 && haystack.toLowerCase().includes(keyword);

  return descMatch || ibanMatch;
}

/** Alleen stortingen (Naar) horen bij een spaarrekening/potje. */
export function transactionMatchesSavingsGoalDeposit(
  tx: Pick<Transaction, "description" | "counterparty" | "amount" | "assignedSavingsGoalId">,
  goal: Pick<SavingsGoal, "id" | "name" | "accountIban">,
  ownIbans: string[] = []
): boolean {
  return (
    transactionMatchesSavingsGoal(tx, goal, ownIbans) && isSavingsDepositTransaction(tx)
  );
}

/** Opnames (Van) van een spaarrekening/potje terug naar betaalrekening. */
export function transactionMatchesSavingsGoalWithdrawal(
  tx: Pick<Transaction, "description" | "counterparty" | "amount" | "assignedSavingsGoalId">,
  goal: Pick<SavingsGoal, "id" | "name" | "accountIban">,
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
  goal: Pick<SavingsGoal, "id" | "name" | "accountIban">,
  ownIbans: string[] = []
): Transaction[] {
  return transactions.filter((tx) =>
    transactionMatchesSavingsGoalDeposit(tx, goal, ownIbans)
  );
}

export function matchingUnlinkedSavingsTransactions(
  transactions: Transaction[],
  goal: Pick<SavingsGoal, "id" | "name" | "accountIban">,
  ownIbans: string[] = [],
  exceptId?: string
): Transaction[] {
  return matchingSavingsTransactions(transactions, goal, ownIbans).filter(
    (tx) => tx.id !== exceptId && isUnlinkedTransaction(tx)
  );
}
