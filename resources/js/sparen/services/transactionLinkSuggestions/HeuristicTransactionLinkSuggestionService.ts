import {
  extractMatchKeywords,
  keywordMatchesHaystack,
  normalizeMatchText,
  textsLikelySameMerchant,
  transactionHaystack,
} from "../../matchKeywords";
import { isPotGoal } from "../../potSettlement";
import {
  isIngSpaarpotTransfer,
  transactionMatchesSavingsGoalDeposit,
  transactionMatchesSavingsGoalWithdrawal,
} from "../../matchSavings";
import { isTransactionInReportingMonth } from "../../month";
import {
  extractSmartKeyword,
  isUnlinkedTransaction,
  transactionMatchesRule,
} from "../../matchRule";
import type {
  BudgetCategoryGroup,
  BudgetItem,
  BudgetType,
  CategoryDefinition,
  Rule,
  SavingsGoal,
  Transaction,
} from "../../types";
import type {
  SuggestionConfidence,
  SuggestionSource,
  TransactionLinkSuggestion,
  TransactionLinkSuggestionContext,
  TransactionLinkSuggestionGroup,
  TransactionLinkSuggestionResult,
  TransactionLinkSuggestionService,
} from "./types";

const CONFIDENCE_RANK: Record<SuggestionConfidence, number> = {
  high: 3,
  medium: 2,
  low: 1,
};

interface HistoryTarget {
  categoryGroup: BudgetCategoryGroup;
  budgetItemId: string;
  count: number;
}

interface LinkedTransactionProfile {
  transaction: Transaction;
  keywords: string[];
  counterpartyKey: string;
  merchantLabel: string;
}

interface MonthEntryTarget {
  entryId: string;
  categoryGroup: BudgetCategoryGroup;
  budgetItemId: string;
  description: string;
  amount: number;
}

function amountsEqual(left: number, right: number): boolean {
  return Math.round(left * 100) === Math.round(right * 100);
}

function buildMonthEntryTargets(budgetItems: BudgetItem[]): MonthEntryTarget[] {
  const targets: MonthEntryTarget[] = [];

  for (const item of budgetItems) {
    if (!item.monthEntries?.length) {
      continue;
    }

    for (const entry of item.monthEntries) {
      const amount = Math.round(Number(entry.amount) * 100) / 100;
      if (amount <= 0) {
        continue;
      }

      targets.push({
        entryId: entry.id,
        categoryGroup: item.group,
        budgetItemId: item.id,
        description: entry.description.trim(),
        amount,
      });
    }
  }

  return targets;
}

function entryDescriptionMatches(entry: MonthEntryTarget, haystack: string): boolean {
  const normalized = normalizeMatchText(entry.description);
  if (normalized.length < 3) {
    return false;
  }
  return haystack.includes(normalized) || keywordMatchesHaystack(normalized, haystack);
}

function findMonthEntrySuggestion(
  tx: Transaction,
  targets: MonthEntryTarget[],
  usedEntryIds: Set<string>,
  categories: CategoryDefinition[]
): TransactionLinkSuggestion | null {
  const txAmount = Math.round(Math.abs(tx.amount) * 100) / 100;
  const haystack = transactionHaystack(tx);

  const candidates = targets.filter(
    (target) => !usedEntryIds.has(target.entryId) && amountsEqual(target.amount, txAmount)
  );

  if (candidates.length === 0) {
    return null;
  }

  const withDescription = candidates.filter((target) => entryDescriptionMatches(target, haystack));
  const pick =
    withDescription[0] ?? (candidates.length === 1 ? candidates[0] : undefined);

  if (!pick) {
    return null;
  }

  usedEntryIds.add(pick.entryId);
  const hasDescription = withDescription.length > 0;
  const label = pick.description || `€ ${pick.amount.toFixed(2)}`;

  return makeSuggestion(
    tx,
    { categoryGroup: pick.categoryGroup, budgetItemId: pick.budgetItemId, count: 1 },
    categories,
    "month_entry",
    hasDescription ? "high" : "medium",
    hasDescription
      ? `Maandregel "${label}" (bedrag + omschrijving)`
      : `Maandregel op bedrag (€ ${pick.amount.toLocaleString("nl-NL", { minimumFractionDigits: 2 })})`,
    pick.description || extractSmartKeyword(tx)
  );
}

function resolveTargetType(
  categoryGroup: BudgetCategoryGroup,
  categories: CategoryDefinition[]
): BudgetType {
  const matched = categories.find((category) => category.name === categoryGroup);
  if (categoryGroup === "Inkomsten" || matched?.type === "inkomsten") {
    return "inkomsten";
  }
  if (categoryGroup === "Spaargeld" || matched?.type === "sparen") {
    return "sparen";
  }
  return "uitgaven";
}

function findFirstBudgetItemInGroup(
  budgetItems: BudgetItem[],
  group: BudgetCategoryGroup,
  preferredType?: BudgetType
): BudgetItem | undefined {
  if (preferredType) {
    const typed = budgetItems.find((item) => item.group === group && item.type === preferredType);
    if (typed) {
      return typed;
    }
  }

  return budgetItems.find((item) => item.group === group);
}

function findMatchingRule(tx: Transaction, rules: Rule[]): Rule | undefined {
  return rules.find((rule) => rule.isActive && transactionMatchesRule(tx, rule));
}

function buildLinkedProfiles(transactions: Transaction[], rules: Rule[]): LinkedTransactionProfile[] {
  const ruleMap = new Map(rules.map((rule) => [rule.id, rule]));

  return transactions
    .filter((tx) => !isUnlinkedTransaction(tx) && tx.budgetItemId)
    .map((tx) => {
      const keywords = new Set(extractMatchKeywords(tx));
      const smartKeyword = extractSmartKeyword(tx).trim().toLowerCase();
      if (smartKeyword.length >= 2) {
        keywords.add(smartKeyword);
      }

      const matchedRule = tx.matchedRuleId ? ruleMap.get(tx.matchedRuleId) : undefined;
      if (matchedRule) {
        keywords.add(matchedRule.keyword.trim().toLowerCase());
      }

      return {
        transaction: tx,
        keywords: [...keywords],
        counterpartyKey: normalizeMatchText(tx.counterparty ?? ""),
        merchantLabel: smartKeyword || normalizeMatchText(tx.description).slice(0, 40),
      };
    });
}

function buildKeywordIndex(
  profiles: LinkedTransactionProfile[]
): Map<string, Map<string, HistoryTarget>> {
  const index = new Map<string, Map<string, HistoryTarget>>();

  for (const profile of profiles) {
    const targetKey = `${profile.transaction.categoryGroup}::${profile.transaction.budgetItemId}`;
    for (const keyword of profile.keywords) {
      const keywordTargets = index.get(keyword) ?? new Map<string, HistoryTarget>();
      const existing = keywordTargets.get(targetKey);
      keywordTargets.set(targetKey, {
        categoryGroup: profile.transaction.categoryGroup,
        budgetItemId: profile.transaction.budgetItemId!,
        count: (existing?.count ?? 0) + 1,
      });
      index.set(keyword, keywordTargets);
    }
  }

  return index;
}

function buildCounterpartyIndex(profiles: LinkedTransactionProfile[]): Map<string, HistoryTarget> {
  const index = new Map<string, HistoryTarget>();

  for (const profile of profiles) {
    if (profile.counterpartyKey.length < 3) {
      continue;
    }

    const targetKey = `${profile.transaction.categoryGroup}::${profile.transaction.budgetItemId}`;
    const existing = index.get(profile.counterpartyKey);
    if (existing && `${existing.categoryGroup}::${existing.budgetItemId}` === targetKey) {
      existing.count += 1;
      continue;
    }

    if (!existing || existing.count === 1) {
      index.set(profile.counterpartyKey, {
        categoryGroup: profile.transaction.categoryGroup,
        budgetItemId: profile.transaction.budgetItemId!,
        count: (existing?.count ?? 0) + 1,
      });
    }
  }

  return index;
}

function pickBestTarget(
  candidates: Array<{ target: HistoryTarget; keyword: string; count: number }>
): { target: HistoryTarget; keyword: string; count: number } | null {
  if (candidates.length === 0) {
    return null;
  }

  return candidates.sort((left, right) => {
    if (right.count !== left.count) {
      return right.count - left.count;
    }
    return right.keyword.length - left.keyword.length;
  })[0];
}

function makeSuggestion(
  tx: Transaction,
  target: HistoryTarget,
  categories: CategoryDefinition[],
  source: SuggestionSource,
  confidence: SuggestionConfidence,
  reason: string,
  keyword?: string,
  ruleId?: string
): TransactionLinkSuggestion {
  const targetType = resolveTargetType(target.categoryGroup, categories);
  const createRule =
    source === "history" || source === "counterparty" || source === "merchant"
      ? {
          name: `Automatisch: ${keyword ?? extractSmartKeyword(tx)}`,
          keyword: keyword ?? extractSmartKeyword(tx),
          matchField: "both" as const,
          targetType,
        }
      : source === "budget_item_name"
        ? {
            name: `Automatisch: ${keyword ?? extractSmartKeyword(tx)}`,
            keyword: keyword ?? extractSmartKeyword(tx),
            matchField: "description" as const,
            targetType,
          }
        : undefined;

  return {
    transactionId: tx.id,
    categoryGroup: target.categoryGroup,
    budgetItemId: target.budgetItemId,
    targetType,
    confidence,
    source,
    reason,
    keyword,
    ruleId,
    createRule,
  };
}

function findKeywordHistorySuggestion(
  tx: Transaction,
  keywordIndex: Map<string, Map<string, HistoryTarget>>,
  categories: CategoryDefinition[]
): TransactionLinkSuggestion | null {
  const haystack = transactionHaystack(tx);
  const candidates: Array<{ target: HistoryTarget; keyword: string; count: number }> = [];

  for (const [keyword, targets] of keywordIndex.entries()) {
    if (!keywordMatchesHaystack(keyword, haystack)) {
      continue;
    }

    for (const target of targets.values()) {
      candidates.push({ target, keyword, count: target.count });
    }
  }

  const best = pickBestTarget(candidates);
  if (!best) {
    return null;
  }

  const confidence: SuggestionConfidence = best.count >= 2 ? "medium" : "low";

  return makeSuggestion(
    tx,
    best.target,
    categories,
    "history",
    confidence,
    best.count >= 2
      ? `${best.count} eerdere koppelingen met "${best.keyword}"`
      : `1 eerdere koppeling met "${best.keyword}"`,
    best.keyword
  );
}

function findCounterpartySuggestion(
  tx: Transaction,
  counterpartyIndex: Map<string, HistoryTarget>,
  categories: CategoryDefinition[]
): TransactionLinkSuggestion | null {
  const counterpartyKey = normalizeMatchText(tx.counterparty ?? "");
  if (counterpartyKey.length < 3) {
    return null;
  }

  const target = counterpartyIndex.get(counterpartyKey);
  if (!target) {
    return null;
  }

  return makeSuggestion(
    tx,
    target,
    categories,
    "counterparty",
    target.count >= 2 ? "medium" : "low",
    target.count >= 2
      ? `${target.count} eerdere koppelingen met dezelfde tegenpartij`
      : `Zelfde tegenpartij als eerdere koppeling`,
    counterpartyKey
  );
}

function findMerchantSuggestion(
  tx: Transaction,
  profiles: LinkedTransactionProfile[],
  categories: CategoryDefinition[]
): TransactionLinkSuggestion | null {
  const txLabel = `${tx.counterparty ?? ""} ${tx.description}`;
  const matches = new Map<string, HistoryTarget>();

  for (const profile of profiles) {
    if (!textsLikelySameMerchant(txLabel, profile.merchantLabel)) {
      continue;
    }

    const key = `${profile.transaction.categoryGroup}::${profile.transaction.budgetItemId}`;
    const existing = matches.get(key);
    matches.set(key, {
      categoryGroup: profile.transaction.categoryGroup,
      budgetItemId: profile.transaction.budgetItemId!,
      count: (existing?.count ?? 0) + 1,
    });
  }

  const best = pickBestTarget(
    [...matches.entries()].map(([key, target]) => ({
      target,
      keyword: key,
      count: target.count,
    }))
  );

  if (!best || best.count < 1) {
    return null;
  }

  return makeSuggestion(
    tx,
    best.target,
    categories,
    "merchant",
    best.count >= 2 ? "medium" : "low",
    best.count >= 2
      ? `${best.count} vergelijkbare eerdere koppelingen`
      : `Lijkt op een eerdere koppeling`,
    extractSmartKeyword(tx)
  );
}

function findBudgetItemNameMatch(
  tx: Transaction,
  budgetItems: BudgetItem[],
  categories: CategoryDefinition[]
): TransactionLinkSuggestion | null {
  const haystack = transactionHaystack(tx);
  const matches = budgetItems
    .filter((item) => item.name.trim().length >= 3 && haystack.includes(item.name.toLowerCase()))
    .sort((left, right) => right.name.length - left.name.length);

  const best = matches[0];
  if (!best) {
    return null;
  }

  return makeSuggestion(
    tx,
    { categoryGroup: best.group, budgetItemId: best.id, count: 1 },
    categories,
    "budget_item_name",
    "low",
    `Omschrijving lijkt op post "${best.name}"`,
    extractSmartKeyword(tx)
  );
}

function findTypeDefaultSuggestion(
  tx: Transaction,
  budgetItems: BudgetItem[],
  categories: CategoryDefinition[]
): TransactionLinkSuggestion | null {
  const preferredGroup: BudgetCategoryGroup =
    tx.type === "Inkomsten"
      ? "Inkomsten"
      : tx.type === "Sparen"
        ? "Spaargeld"
        : "Overige Kosten";

  const preferredType: BudgetType =
    tx.type === "Inkomsten" ? "inkomsten" : tx.type === "Sparen" ? "sparen" : "uitgaven";

  const item =
    findFirstBudgetItemInGroup(budgetItems, preferredGroup, preferredType) ??
    findFirstBudgetItemInGroup(budgetItems, preferredGroup);

  if (!item) {
    return null;
  }

  return makeSuggestion(
    tx,
    { categoryGroup: item.group, budgetItemId: item.id, count: 1 },
    categories,
    "type_default",
    "low",
    `Standaard voor ${tx.type.toLowerCase()} (controleer even)`,
    extractSmartKeyword(tx)
  );
}

function suggestForTransaction(
  tx: Transaction,
  context: TransactionLinkSuggestionContext,
  keywordIndex: Map<string, Map<string, HistoryTarget>>,
  counterpartyIndex: Map<string, HistoryTarget>,
  linkedProfiles: LinkedTransactionProfile[],
  monthEntryTargets: MonthEntryTarget[],
  usedMonthEntryIds: Set<string>
): TransactionLinkSuggestion | null {
  const matchingRule = findMatchingRule(tx, context.rules);
  if (matchingRule) {
    const budgetItemId =
      matchingRule.targetBudgetItemId ||
      findFirstBudgetItemInGroup(context.budgetItems, matchingRule.targetGroup, matchingRule.targetType)?.id;

    if (!budgetItemId) {
      return null;
    }

    return {
      transactionId: tx.id,
      categoryGroup: matchingRule.targetGroup,
      budgetItemId,
      targetType: matchingRule.targetType,
      confidence: "high",
      source: "rule",
      reason: `Koppelregel "${matchingRule.name}"`,
      keyword: matchingRule.keyword,
      ruleId: matchingRule.id,
    };
  }

  for (const goal of context.savingsGoals) {
    if (isPotGoal(goal)) {
      continue;
    }
    if (!transactionMatchesSavingsGoalDeposit(tx, goal, context.ownIbans) &&
      !transactionMatchesSavingsGoalWithdrawal(tx, goal, context.ownIbans)) {
      continue;
    }

    const budgetItemId =
      goal.categoryBudgetItemId ||
      findFirstBudgetItemInGroup(context.budgetItems, "Spaargeld", "sparen")?.id ||
      "spaar-1";

    return {
      transactionId: tx.id,
      categoryGroup: "Spaargeld",
      budgetItemId,
      targetType: "sparen",
      confidence: "high",
      source: "savings_goal",
      reason: `Spaardoel "${goal.name}"`,
      keyword: goal.name,
    };
  }

  const monthEntrySuggestion = findMonthEntrySuggestion(
    tx,
    monthEntryTargets,
    usedMonthEntryIds,
    context.categories
  );
  if (monthEntrySuggestion) {
    return monthEntrySuggestion;
  }

  const counterpartySuggestion = findCounterpartySuggestion(tx, counterpartyIndex, context.categories);
  if (counterpartySuggestion) {
    return counterpartySuggestion;
  }

  const keywordHistorySuggestion = findKeywordHistorySuggestion(tx, keywordIndex, context.categories);
  if (keywordHistorySuggestion) {
    return keywordHistorySuggestion;
  }

  const merchantSuggestion = findMerchantSuggestion(tx, linkedProfiles, context.categories);
  if (merchantSuggestion) {
    return merchantSuggestion;
  }

  const budgetItemSuggestion = findBudgetItemNameMatch(tx, context.budgetItems, context.categories);
  if (budgetItemSuggestion) {
    return budgetItemSuggestion;
  }

  return findTypeDefaultSuggestion(tx, context.budgetItems, context.categories);
}

function mergeGroupConfidence(
  current: SuggestionConfidence,
  next: SuggestionConfidence
): SuggestionConfidence {
  return CONFIDENCE_RANK[next] > CONFIDENCE_RANK[current] ? next : current;
}

function groupKey(categoryGroup: BudgetCategoryGroup, budgetItemId: string): string {
  return `${categoryGroup}::${budgetItemId}`;
}

function buildGroups(suggestions: TransactionLinkSuggestion[]): TransactionLinkSuggestionGroup[] {
  const grouped = new Map<string, TransactionLinkSuggestionGroup>();

  for (const suggestion of suggestions) {
    const id = groupKey(suggestion.categoryGroup, suggestion.budgetItemId);
    const existing = grouped.get(id);

    if (!existing) {
      grouped.set(id, {
        id,
        categoryGroup: suggestion.categoryGroup,
        budgetItemId: suggestion.budgetItemId,
        targetType: suggestion.targetType,
        confidence: suggestion.confidence,
        source: suggestion.source,
        reason: suggestion.reason,
        keyword: suggestion.keyword,
        ruleId: suggestion.ruleId,
        suggestions: [suggestion],
        defaultSelected: suggestion.confidence !== "low" || suggestion.source === "counterparty",
        defaultCreateRule:
          (suggestion.source === "history" ||
            suggestion.source === "counterparty" ||
            suggestion.source === "merchant") &&
          suggestion.confidence !== "low",
        createRule: suggestion.createRule,
      });
      continue;
    }

    existing.suggestions.push(suggestion);
    existing.confidence = mergeGroupConfidence(existing.confidence, suggestion.confidence);
    existing.defaultSelected = existing.confidence !== "low" || existing.source === "counterparty";

    if (!existing.keyword && suggestion.keyword) {
      existing.keyword = suggestion.keyword;
    }
    if (!existing.ruleId && suggestion.ruleId) {
      existing.ruleId = suggestion.ruleId;
    }
    if (!existing.createRule && suggestion.createRule) {
      existing.createRule = suggestion.createRule;
      existing.defaultCreateRule =
        (suggestion.source === "history" ||
          suggestion.source === "counterparty" ||
          suggestion.source === "merchant") &&
        existing.confidence !== "low";
    }

    if (existing.source !== suggestion.source) {
      existing.reason = `${existing.suggestions.length} transacties via meerdere signalen`;
    }
  }

  return [...grouped.values()].sort((left, right) => {
    const confidenceDiff = CONFIDENCE_RANK[right.confidence] - CONFIDENCE_RANK[left.confidence];
    if (confidenceDiff !== 0) {
      return confidenceDiff;
    }

    return left.categoryGroup.localeCompare(right.categoryGroup, "nl");
  });
}

export class HeuristicTransactionLinkSuggestionService implements TransactionLinkSuggestionService {
  readonly id = "heuristic";
  readonly label = "Regels, maandregels, historie & tegenpartij";

  suggest(context: TransactionLinkSuggestionContext): TransactionLinkSuggestionResult {
    const unlinked = context.transactions.filter((tx) => {
      if (!isUnlinkedTransaction(tx) || isIngSpaarpotTransfer(tx)) {
        return false;
      }
      if (context.reportingMonth) {
        return isTransactionInReportingMonth(tx, context.reportingMonth);
      }
      return true;
    });
    const linkedProfiles = buildLinkedProfiles(context.transactions, context.rules);
    const keywordIndex = buildKeywordIndex(linkedProfiles);
    const counterpartyIndex = buildCounterpartyIndex(linkedProfiles);
    const monthEntryTargets = buildMonthEntryTargets(context.budgetItems);
    const usedMonthEntryIds = new Set<string>();
    const suggestions: TransactionLinkSuggestion[] = [];
    const suggestedIds = new Set<string>();

    for (const tx of unlinked) {
      const suggestion = suggestForTransaction(
        tx,
        context,
        keywordIndex,
        counterpartyIndex,
        linkedProfiles,
        monthEntryTargets,
        usedMonthEntryIds
      );
      if (!suggestion) {
        continue;
      }

      suggestions.push(suggestion);
      suggestedIds.add(tx.id);
    }

    return {
      groups: buildGroups(suggestions),
      unsuggestedTransactionIds: unlinked
        .filter((tx) => !suggestedIds.has(tx.id))
        .map((tx) => tx.id),
      meta: {
        providerId: this.id,
        providerLabel: this.label,
        evaluatedAt: new Date().toISOString(),
        unlinkedCount: unlinked.length,
      },
    };
  }
}
