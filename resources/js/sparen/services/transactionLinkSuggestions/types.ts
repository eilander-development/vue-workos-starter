import type {
  BudgetCategoryGroup,
  BudgetItem,
  BudgetType,
  CategoryDefinition,
  MonthlyBudget,
  Rule,
  SavingsGoal,
  Transaction,
} from "../../types";
import type { RuleMatchField } from "../../matchRule";

export type SuggestionConfidence = "high" | "medium" | "low";

export type SuggestionSource =
  | "rule"
  | "savings_goal"
  | "month_entry"
  | "history"
  | "counterparty"
  | "merchant"
  | "budget_item_name"
  | "type_default";

export interface SuggestedRuleCreation {
  name: string;
  keyword: string;
  matchField: RuleMatchField;
  targetType: BudgetType;
}

export interface TransactionLinkSuggestion {
  transactionId: string;
  categoryGroup: BudgetCategoryGroup;
  budgetItemId: string;
  targetType: BudgetType;
  confidence: SuggestionConfidence;
  source: SuggestionSource;
  reason: string;
  keyword?: string;
  ruleId?: string;
  createRule?: SuggestedRuleCreation;
}

export interface TransactionLinkSuggestionGroup {
  id: string;
  categoryGroup: BudgetCategoryGroup;
  budgetItemId: string;
  targetType: BudgetType;
  confidence: SuggestionConfidence;
  source: SuggestionSource;
  reason: string;
  keyword?: string;
  ruleId?: string;
  suggestions: TransactionLinkSuggestion[];
  defaultSelected: boolean;
  defaultCreateRule: boolean;
  createRule?: SuggestedRuleCreation;
}

export interface TransactionLinkSuggestionResult {
  groups: TransactionLinkSuggestionGroup[];
  unsuggestedTransactionIds: string[];
  meta: {
    providerId: string;
    providerLabel: string;
    evaluatedAt: string;
    unlinkedCount: number;
  };
}

export interface TransactionLinkSuggestionContext {
  transactions: Transaction[];
  rules: Rule[];
  savingsGoals: SavingsGoal[];
  budgetItems: BudgetItem[];
  categories: CategoryDefinition[];
  ownIbans: string[];
  /** Huidige rapportagemaand: filter transacties + maandregels */
  reportingMonth?: MonthlyBudget;
}

export interface TransactionLinkSuggestionService {
  readonly id: string;
  readonly label: string;
  suggest(
    context: TransactionLinkSuggestionContext
  ): TransactionLinkSuggestionResult | Promise<TransactionLinkSuggestionResult>;
}

export interface AutoProcessSaveAssignment {
  transactionId: string;
  categoryGroup: BudgetCategoryGroup;
  budgetItemId: string;
  targetType: BudgetType;
  createRule?: SuggestedRuleCreation;
}

/** @deprecated Prefer AutoProcessSaveAssignment for per-transaction saves */
export interface AutoProcessSaveGroup {
  groupId: string;
  transactionIds: string[];
  categoryGroup: BudgetCategoryGroup;
  budgetItemId: string;
  targetType: BudgetType;
  createRule?: SuggestedRuleCreation;
}
