import type { BudgetType, Rule, Transaction } from "./types";

export type RuleMatchField = "description" | "counterparty" | "both";
export type RuleAmountDirection = "in" | "out" | "any";

export function isLinkExcludedTransaction(
  tx: Pick<Transaction, "linkExcluded">
): boolean {
  return Boolean(tx.linkExcluded);
}

export function isUnlinkedTransaction(
  tx: Pick<Transaction, "budgetItemId" | "categoryGroup" | "linkExcluded">
): boolean {
  if (isLinkExcludedTransaction(tx)) {
    return false;
  }

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

/** Inkomsten = alleen erbij; uitgaven = alleen eraf; sparen = alleen stortingen (eraf). */
export function ruleAmountDirection(targetType: BudgetType): RuleAmountDirection {
  if (targetType === "inkomsten") return "in";
  if (targetType === "uitgaven") return "out";
  return "out";
}

export function transactionMatchesRuleDirection(
  tx: Pick<Transaction, "amount">,
  direction: RuleAmountDirection
): boolean {
  if (direction === "in") return tx.amount > 0;
  if (direction === "out") return tx.amount < 0;
  return true;
}

export function transactionMatchesRule(
  tx: Pick<Transaction, "description" | "counterparty" | "amount">,
  rule: Pick<Rule, "keyword" | "matchField" | "targetType">
): boolean {
  return (
    transactionMatchesKeyword(tx, rule.keyword, rule.matchField) &&
    transactionMatchesRuleDirection(tx, ruleAmountDirection(rule.targetType))
  );
}

export function matchingUnlinkedTransactions(
  transactions: Transaction[],
  keyword: string,
  matchField: RuleMatchField,
  exceptId?: string,
  targetType?: BudgetType
): Transaction[] {
  const direction = targetType ? ruleAmountDirection(targetType) : "any";
  return transactions.filter(
    (tx) =>
      tx.id !== exceptId &&
      isUnlinkedTransaction(tx) &&
      transactionMatchesKeyword(tx, keyword, matchField) &&
      transactionMatchesRuleDirection(tx, direction)
  );
}

export function transactionsMatchingRule(
  transactions: Transaction[],
  rule: Pick<Rule, "keyword" | "matchField" | "targetType">,
  options: { ignoreDirection?: boolean } = {}
): Transaction[] {
  return transactions.filter((tx) => {
    if (!transactionMatchesKeyword(tx, rule.keyword, rule.matchField)) {
      return false;
    }
    if (options.ignoreDirection) {
      return true;
    }
    return transactionMatchesRuleDirection(tx, ruleAmountDirection(rule.targetType));
  });
}

export function textLooksLikeRule(
  text: string,
  rule: Pick<Rule, "name" | "keyword">
): boolean {
  const query = text.trim().toLowerCase();
  const keyword = rule.keyword.trim().toLowerCase();
  if (query.length < 2 || keyword.length < 2) {
    return false;
  }

  if (query.includes(keyword) || keyword.includes(query)) {
    return true;
  }

  return rule.name.trim().toLowerCase().includes(query);
}

export function haystackContainsQuery(
  tx: Pick<Transaction, "description" | "counterparty">,
  query: string
): boolean {
  const needle = query.trim().toLowerCase();
  if (needle.length < 2) {
    return false;
  }

  return (
    tx.description.toLowerCase().includes(needle) ||
    (tx.counterparty?.toLowerCase().includes(needle) ?? false)
  );
}

export type RuleTestHit = {
  rule: Rule;
  via: "keyword" | "transaction";
  sampleCount: number;
};

export function findRulesForTestInput(
  input: string,
  rules: Rule[],
  transactions: Transaction[]
): RuleTestHit[] {
  const query = input.trim().toLowerCase();
  if (query.length < 2) {
    return [];
  }

  const active = rules.filter((rule) => rule.isActive);
  const matchingTxs = transactions.filter((tx) => haystackContainsQuery(tx, query));
  const hits = new Map<string, RuleTestHit>();

  for (const rule of active) {
    const keywordHit = textLooksLikeRule(input, rule);
    const txHits = matchingTxs.filter((tx) =>
      transactionMatchesKeyword(tx, rule.keyword, rule.matchField)
    );
    if (!keywordHit && txHits.length === 0) {
      continue;
    }

    hits.set(rule.id, {
      rule,
      via: keywordHit ? "keyword" : "transaction",
      sampleCount: Math.max(
        txHits.length,
        transactionsMatchingRule(transactions, rule, { ignoreDirection: true }).length
      ),
    });
  }

  return [...hits.values()].sort((left, right) => right.sampleCount - left.sampleCount);
}

export function transactionsMatchingTestInput(
  input: string,
  transactions: Transaction[]
): Transaction[] {
  return transactions.filter((tx) => haystackContainsQuery(tx, input));
}

const IBAN_PATTERN = /^[A-Z]{2}\d{2}[A-Z0-9]{10,}$/i;

export function isIbanLike(value: string): boolean {
  return IBAN_PATTERN.test(value.replace(/\s+/g, ""));
}

export function extractSmartKeyword(tx: Transaction): string {
  const counterparty = tx.counterparty?.trim() ?? "";
  if (counterparty.length > 2 && !isIbanLike(counterparty)) {
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
