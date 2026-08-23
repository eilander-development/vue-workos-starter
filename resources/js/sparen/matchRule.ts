import type { Transaction } from "./types";

export type RuleMatchField = "description" | "counterparty" | "both";

export function isUnlinkedTransaction(tx: Pick<Transaction, "budgetItemId" | "categoryGroup">): boolean {
  return !tx.budgetItemId || tx.categoryGroup === "Ongecategoriseerd";
}

export function transactionMatchesKeyword(
  tx: Pick<Transaction, "description" | "counterparty">,
  keyword: string,
  matchField: RuleMatchField
): boolean {
  const needle = keyword.trim().toLowerCase();
  if (needle.length < 2) {
    return false;
  }

  const inDescription = tx.description.toLowerCase().includes(needle);
  const inCounterparty = tx.counterparty?.toLowerCase().includes(needle) ?? false;

  if (matchField === "description") {
    return inDescription;
  }
  if (matchField === "counterparty") {
    return inCounterparty;
  }

  return inDescription || inCounterparty;
}

export function matchingUnlinkedTransactions(
  transactions: Transaction[],
  keyword: string,
  matchField: RuleMatchField,
  exceptId?: string
): Transaction[] {
  return transactions.filter(
    (tx) =>
      tx.id !== exceptId &&
      isUnlinkedTransaction(tx) &&
      transactionMatchesKeyword(tx, keyword, matchField)
  );
}

const IBAN_PATTERN = /^[A-Z]{2}\d{2}[A-Z0-9]{10,}$/i;

export function extractSmartKeyword(tx: Transaction): string {
  const counterparty = tx.counterparty?.trim() ?? "";
  if (counterparty.length > 2 && !IBAN_PATTERN.test(counterparty.replace(/\s+/g, ""))) {
    return counterparty;
  }

  const clean = tx.description
    .replace(/^(BCK\*|CCV\*|GEA\*|PIN\*|SEPA\s*)/i, "")
    .replace(/(NLD\s*Google\s*Pay|Google\s*Pay|Betaalpas|iDeal|via\s*.*|Incasso.*)/i, "")
    .replace(/\bNL\d{2}[A-Z0-9]{10,}\b/gi, "")
    .replace(/\d{4,}/g, "")
    .trim();
  const words = clean.split(/\s+/).filter((word) => word.length > 1);

  return words.slice(0, 2).join(" ") || tx.description.slice(0, 15);
}
