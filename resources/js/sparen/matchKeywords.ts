import type { Transaction } from "./types";
import { extractSmartKeyword, isIbanLike } from "./matchRule";

export function normalizeMatchText(value: string): string {
  return value
    .toLowerCase()
    .replace(/^(bck\*|ccv\*|gea\*|pin\*|sepa\s*)/i, "")
    .replace(
      /\b(nld\s*google\s*pay|google\s*pay|betaalpas|ideal|incasso|doorlopende\s*incasso|automatis(?:che|ch)\s*incasso)\b/gi,
      " "
    )
    .replace(/\bNL\d{2}[A-Z0-9]{10,}\b/gi, " ")
    .replace(/\d{4,}/g, " ")
    .replace(/[^a-zà-ÿ0-9\s]/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
}

export function transactionHaystack(tx: Pick<Transaction, "description" | "counterparty">): string {
  return normalizeMatchText(`${tx.description} ${tx.counterparty ?? ""}`);
}

export function extractMatchKeywords(tx: Transaction): string[] {
  const keywords = new Set<string>();
  const counterparty = tx.counterparty?.trim() ?? "";

  if (counterparty.length > 2 && !isIbanLike(counterparty)) {
    keywords.add(counterparty.toLowerCase());
    const normalizedCounterparty = normalizeMatchText(counterparty);
    if (normalizedCounterparty.length >= 3) {
      keywords.add(normalizedCounterparty);
    }

    for (const word of normalizedCounterparty.split(" ").filter((part) => part.length >= 4)) {
      keywords.add(word);
    }
  }

  const smartKeyword = extractSmartKeyword(tx).trim().toLowerCase();
  if (smartKeyword.length >= 2) {
    keywords.add(smartKeyword);
  }

  for (const word of normalizeMatchText(tx.description).split(" ").filter((part) => part.length >= 4)) {
    keywords.add(word);
  }

  return [...keywords].filter((keyword) => keyword.length >= 2);
}

export function keywordMatchesHaystack(keyword: string, haystack: string): boolean {
  const needle = keyword.trim().toLowerCase();
  if (needle.length < 2) {
    return false;
  }

  if (haystack.includes(needle)) {
    return true;
  }

  if (needle.length >= 4) {
    return haystack.split(" ").some((word) => word.startsWith(needle) || needle.startsWith(word));
  }

  return false;
}

export function textsLikelySameMerchant(left: string, right: string): boolean {
  const normalizedLeft = normalizeMatchText(left);
  const normalizedRight = normalizeMatchText(right);
  if (!normalizedLeft || !normalizedRight) {
    return false;
  }

  if (normalizedLeft === normalizedRight) {
    return true;
  }

  if (normalizedLeft.includes(normalizedRight) || normalizedRight.includes(normalizedLeft)) {
    return Math.min(normalizedLeft.length, normalizedRight.length) >= 4;
  }

  const leftWords = normalizedLeft.split(" ").filter((word) => word.length >= 4);
  const rightWords = new Set(normalizedRight.split(" ").filter((word) => word.length >= 4));
  return leftWords.some((word) => rightWords.has(word));
}
