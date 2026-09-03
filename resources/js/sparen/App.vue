<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import type {
  ActiveTab,
  MonthlyBudget,
  Transaction,
  Rule,
  BankAccount,
  SavingsRow,
  BudgetItem,
  BudgetCategoryGroup,
  SavingsGoal,
  CategoryDefinition,
  BudgetType,
  SaveState,
} from "./types";
import {
  INITIAL_MONTHLY_BUDGETS,
  INITIAL_RULES,
  INITIAL_SAVINGS_HISTORY,
  INITIAL_SAVINGS_GOALS,
  DEFAULT_CATEGORY_DEFINITIONS,
} from "./data/mockBudgetData";
import Sidebar from "./components/Sidebar.vue";
import Header from "./components/Header.vue";
import BottomNav from "./components/BottomNav.vue";
import DashboardView from "./components/DashboardView.vue";
import BudgetSpreadsheetView from "./components/BudgetSpreadsheetView.vue";
import ExpensesView from "./components/ExpensesView.vue";
import IncomeView from "./components/IncomeView.vue";
import SavingsTrackerView from "./components/SavingsTrackerView.vue";
import TransactionsView from "./components/TransactionsView.vue";
import EnableBankingView from "./components/EnableBankingView.vue";
import CategoriesView from "./components/CategoriesView.vue";
import KoppelregelsView from "./components/KoppelregelsView.vue";
import YearOverviewView from "./components/YearOverviewView.vue";
import SettingsView from "./components/SettingsView.vue";
import AddTransactionModal from "./components/AddTransactionModal.vue";
import AddBudgetItemModal from "./components/AddBudgetItemModal.vue";
import EditBudgetItemModal from "./components/EditBudgetItemModal.vue";
import ManageCategoryModal from "./components/ManageCategoryModal.vue";
import AddRuleModal from "./components/AddRuleModal.vue";
import AddSavingsGoalModal from "./components/AddSavingsGoalModal.vue";
import ItemTransactionsModal from "./components/ItemTransactionsModal.vue";
import AutoProcessTransactionsModal from "./components/AutoProcessTransactionsModal.vue";
import ToastStack from "./components/Toast.vue";
import PotCompensationBanner from "./components/PotCompensationBanner.vue";
import PotSettlementModal from "./components/PotSettlementModal.vue";
import type { AutoProcessSaveAssignment } from "./services/transactionLinkSuggestions";
import { useToasts } from "./composables/useToasts";
import {
  loadSparenState,
  saveBudgetItem,
  deleteBudgetItem,
  saveTransaction,
  saveTransactions,
  deleteTransactionRecord,
  saveRule,
  deleteRuleRecord,
  saveCategoryRecord,
  deleteCategoryRecord,
  saveSavingsGoalRecord,
  deleteSavingsGoalRecord,
  syncSparenBank,
} from "./api";
import { useSparenRoute } from "./navigation";
import {
  applyReportingSettings,
  formatReportingPeriodLabel,
  isTransactionInReportingMonth,
  reportingPeriodForMonth,
} from "./month";
import {
  isUnlinkedTransaction,
  matchingUnlinkedTransactions,
  transactionMatchesRule,
} from "./matchRule";
import { isSavingsCashflowTransfer, transactionMatchesSavingsGoalDeposit } from "./matchSavings";
import {
  goalBudgetItemIds,
  isPotGoal,
  applyPotTransferLinkExclusion,
  potLinkedBudgetItemIds,
  potsNeedingCompensation,
  potGoalsLinkedToItem,
  computePotSettlement,
  type PotCompensationNeed,
} from "./potSettlement";
import { transactionMatchesBudgetItem } from "./budgetPayment";

const DISCONNECTED_CHECKING_ACCOUNT: BankAccount = {
  id: "checking-unlinked",
  name: "ING Betaalrekening",
  bankName: "ING Bank",
  iban: "",
  type: "checking",
  balance: 0,
  availableBalance: 0,
  currency: "EUR",
  lastSync: "",
  status: "disconnected",
  syncCountToday: 0,
  consentValidUntil: null,
  consentDaysRemaining: null,
  consentExpired: true,
};

const { activeTab, setActiveTab } = useSparenRoute();
const selectedMonthId = ref("aug");
const selectedYear = ref(2026);
const baseMonthlyBudgets = ref<MonthlyBudget[]>(INITIAL_MONTHLY_BUDGETS);
const categories = ref<CategoryDefinition[]>(DEFAULT_CATEGORY_DEFINITIONS);
const transactions = ref<Transaction[]>([]);
const rules = ref<Rule[]>(INITIAL_RULES);
const bankAccounts = ref<BankAccount[]>([]);
const savingsGoals = ref<SavingsGoal[]>(INITIAL_SAVINGS_GOALS);
const savingsHistory = ref<SavingsRow[]>(INITIAL_SAVINGS_HISTORY);
const isSyncing = ref(false);
const isMobileDrawerOpen = ref(false);

const isAddTxModalOpen = ref(false);
const isAutoProcessModalOpen = ref(false);
const isAddBudgetItemModalOpen = ref(false);
const addBudgetItemDefaultGroup = ref<BudgetCategoryGroup | undefined>(undefined);
const editingBudgetItem = ref<BudgetItem | null>(null);
const isCategoryModalOpen = ref(false);
const editingCategory = ref<CategoryDefinition | null>(null);
const isAddRuleModalOpen = ref(false);
const editingRule = ref<Rule | null>(null);
const isAddSavingsGoalModalOpen = ref(false);
const editingSavingsGoal = ref<SavingsGoal | null>(null);
const itemTransactionsModalItem = ref<BudgetItem | null>(null);
const potDetailGoal = ref<SavingsGoal | null>(null);
const transactionsOpenFilter = ref<"ALL" | "UNLINKED">("ALL");

const initialRuleKeyword = ref("");
const initialRuleGroup = ref<BudgetCategoryGroup>("Dagelijks Leven");
const initialRuleBudgetItemId = ref("");
const isReady = ref(false);
const saveState = ref<SaveState>("idle");
const { toasts, notify, dismiss } = useToasts();

function applyRemoteState(data: Record<string, unknown>) {
  if (data.reporting) {
    applyReportingSettings(data.reporting as { startDayOfMonth: number });
    const reporting = data.reporting as {
      defaultMonthId?: string;
      defaultYear?: number;
    };
    if (reporting.defaultMonthId) {
      selectedMonthId.value = reporting.defaultMonthId;
    }
    if (reporting.defaultYear) {
      selectedYear.value = reporting.defaultYear;
    }
  }
  if (data.categories) categories.value = data.categories as CategoryDefinition[];
  if (data.monthlyBudgets) baseMonthlyBudgets.value = data.monthlyBudgets as MonthlyBudget[];
  if (data.transactions) transactions.value = data.transactions as Transaction[];
  if (data.rules) rules.value = data.rules as Rule[];
  if (data.bankAccounts) bankAccounts.value = data.bankAccounts as BankAccount[];
  if (data.savingsGoals) savingsGoals.value = data.savingsGoals as SavingsGoal[];
  if (data.savingsHistory) savingsHistory.value = data.savingsHistory as SavingsRow[];
}

function reconcilePotDepositFlags() {
  const previous = transactions.value;
  const next = applyRulesToTransactions(previous, rules.value, savingsGoals.value);
  transactions.value = next;
  void persistChangedTransactions(previous, next);
}

onMounted(() => {
  loadSparenState()
    .then((data) => {
      applyRemoteState(data);
      reconcilePotDepositFlags();
    })
    .catch(() =>
      notify(
        "error",
        "Gegevens laden mislukt",
        "De app toont nu voorbeeldcijfers. Herlaad de pagina om opnieuw te proberen."
      )
    )
    .finally(() => {
      isReady.value = true;
    });
});

async function persistChangedTransactions(previous: Transaction[], next: Transaction[]) {
  const beforeById = new Map(previous.map((tx) => [tx.id, tx]));
  const changed = next.filter((tx) => {
    const before = beforeById.get(tx.id);
    if (!before) {
      return true;
    }

    return (
      before.budgetItemId !== tx.budgetItemId ||
      before.categoryGroup !== tx.categoryGroup ||
      before.type !== tx.type ||
      before.matchedRuleId !== tx.matchedRuleId ||
      before.linkExcluded !== tx.linkExcluded ||
      before.linkExclusionReason !== tx.linkExclusionReason ||
      before.description !== tx.description ||
      before.amount !== tx.amount ||
      before.date !== tx.date
    );
  });

  await saveTransactions(changed);
}

async function runSave(
  work: () => Promise<unknown>,
  success?: { title: string; detail?: string; variant?: "success" | "info" },
  errorTitle = "Opslaan mislukt"
) {
  saveState.value = "saving";
  try {
    await work();
    saveState.value = "saved";
    if (success?.title) {
      notify(success.variant ?? "success", success.title, success.detail);
    }
  } catch (error) {
    saveState.value = "error";
    notify(
      "error",
      errorTitle,
      error instanceof Error ? error.message : "De laatste wijziging staat nog niet in de database."
    );
    throw error;
  }
}

watch(saveState, (value) => {
  if (value !== "saved") {
    return;
  }
  window.setTimeout(() => {
    saveState.value = "idle";
  }, 2500);
});

function applyRulesToTransactions(txs: Transaction[], rls: Rule[], sGoals: SavingsGoal[]) {
  const ownIbans = bankAccounts.value.map((account) => account.iban).filter(Boolean);

  return txs.map((tx) => {
    const savingsApplied = applyPotTransferLinkExclusion(tx, sGoals, ownIbans);
    if (savingsApplied.linkExcluded) {
      return savingsApplied;
    }

    for (const goal of sGoals) {
      if (isPotGoal(goal)) {
        continue;
      }
      if (!isUnlinkedTransaction(tx) || !transactionMatchesSavingsGoalDeposit(tx, goal, ownIbans)) {
        continue;
      }

      const budgetItemId = goalBudgetItemIds(goal)[0];
      return {
        ...tx,
        type: "Sparen" as const,
        categoryGroup: "Spaargeld" as const,
        budgetItemId: budgetItemId || tx.budgetItemId,
        counterparty: goal.bankName || goal.name,
        linkExcluded: false,
        linkExclusionReason: undefined,
      };
    }

    const match = rls.find((r) => r.isActive && transactionMatchesRule(tx, r));

    if (match && isUnlinkedTransaction(tx)) {
      return {
        ...tx,
        categoryGroup: match.targetGroup,
        type:
          match.targetType === "inkomsten"
            ? ("Inkomsten" as const)
            : match.targetType === "sparen"
              ? ("Sparen" as const)
              : ("Uitgave" as const),
        budgetItemId: match.targetBudgetItemId || tx.budgetItemId,
        matchedRuleId: match.id,
      };
    }

    return tx;
  });
}

const monthlyBudgets = computed(() => {
  const potLinkedIds = potLinkedBudgetItemIds(savingsGoals.value);

  return baseMonthlyBudgets.value.map((mb) => {
    const txsInMonth = transactions.value.filter((t) => isTransactionInReportingMonth(t, mb));

    const updatedItems = mb.items.map((item) => {
      const matchingTxs = txsInMonth.filter((t) => {
        if (!transactionMatchesBudgetItem(t, item)) {
          return false;
        }
        if (item.type === "uitgaven" && isSavingsCashflowTransfer(t)) {
          return false;
        }
        return true;
      });

      const totalFromTxs = matchingTxs.reduce((sum, t) => sum + Math.abs(t.amount), 0);
      const paymentCount = matchingTxs.length;
      const isPotEnvelope = potLinkedIds.has(item.id) && item.type === "uitgaven";

      if (isPotEnvelope) {
        const envelopePaid = item.actual ?? 0;
        const hasPotActivity = totalFromTxs > 0;
        return {
          ...item,
          paidOrReceived: hasPotActivity ? envelopePaid : 0,
          shadowSpent: totalFromTxs,
          paymentCount,
          isPaid: hasPotActivity && envelopePaid > 0,
        };
      }

      return {
        ...item,
        paidOrReceived: totalFromTxs,
        shadowSpent: undefined,
        paymentCount,
        isPaid: totalFromTxs >= item.actual && item.actual > 0,
      };
    });

    return {
      ...mb,
      items: updatedItems,
    };
  });
});

const currentMonth = computed(
  () =>
    monthlyBudgets.value.find(
      (m) => m.monthId === selectedMonthId.value && m.year === selectedYear.value
    ) ||
    monthlyBudgets.value.find((m) => m.monthId === selectedMonthId.value) ||
    monthlyBudgets.value[0]
);

const currentReportingPeriodLabel = computed(() => {
  if (!currentMonth.value) {
    return "";
  }

  const period =
    currentMonth.value.periodStart && currentMonth.value.periodEnd
      ? { start: currentMonth.value.periodStart, end: currentMonth.value.periodEnd }
      : reportingPeriodForMonth(currentMonth.value);

  return formatReportingPeriodLabel(period);
});

const potCompensationNeeds = computed(() =>
  potsNeedingCompensation(currentMonth.value, transactions.value, savingsGoals.value)
);

const potDetailSettlement = computed(() => {
  if (!potDetailGoal.value || !currentMonth.value) return null;
  return computePotSettlement(potDetailGoal.value, currentMonth.value, transactions.value);
});

function openPotDetail(goal: SavingsGoal) {
  itemTransactionsModalItem.value = null;
  potDetailGoal.value = goal;
}

function openPotFromCompensation(need: PotCompensationNeed) {
  openPotDetail(need.goal);
}

function openPotFromBudgetItem(item: BudgetItem) {
  const goal = potGoalsLinkedToItem(item.id, savingsGoals.value)[0];
  if (goal) {
    openPotDetail(goal);
  }
}

const primaryBankAccount = computed(() => {
  const checking = bankAccounts.value.filter((account) => account.type === "checking");
  if (checking.length === 0) {
    return bankAccounts.value[0] ?? DISCONNECTED_CHECKING_ACCOUNT;
  }

  return checking.reduce((latest, account) => {
    const latestAt = latest.lastSyncedAt ?? "";
    const accountAt = account.lastSyncedAt ?? "";
    return accountAt > latestAt ? account : latest;
  });
});

function budgetItemName(itemId?: string) {
  return (
    monthlyBudgets.value.flatMap((month) => month.items).find((item) => item.id === itemId)?.name ??
    "begrotingspost"
  );
}

function euro(amount: number) {
  return `€ ${Math.abs(amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 })}`;
}

async function persistBudgetChange(
  itemId: string,
  payload: Record<string, unknown>,
  successTitle: string,
  successDetail?: string
) {
  saveState.value = "saving";
  try {
    await saveBudgetItem(itemId, payload);
    saveState.value = "saved";
    if (successTitle) {
      notify("success", successTitle, successDetail);
    }
  } catch {
    saveState.value = "error";
    notify("error", "Begrotingspost niet opgeslagen", "De wijziging staat nog niet in de database.");
  }
}

async function handleBankSync() {
  isSyncing.value = true;
  try {
    const result = await syncSparenBank();

    if (result?.needsConnect) {
      notify(
        "info",
        "Bankkoppeling nodig",
        "Je wordt doorgestuurd naar ING om opnieuw toegang te geven."
      );
      return;
    }

    if (result?.state) {
      applyRemoteState(result.state);
    }

    const stats = result?.imported;
    const details = stats
      ? [
          `${stats.imported ?? 0} nieuw`,
          `${stats.duplicates ?? 0} al bekend`,
          ...(stats.time_backfilled ? [`${stats.time_backfilled} tijden aangevuld`] : []),
        ].join(" · ")
      : undefined;

    notify("success", "Bank gesynchroniseerd", details);
  } catch (error) {
    notify("error", "Synchronisatie mislukt", error instanceof Error ? error.message : undefined);
  } finally {
    isSyncing.value = false;
  }
}

function handleAddTransaction(txData: Omit<Transaction, "id">) {
  const newTx: Transaction = {
    ...txData,
    id: `tx-${Date.now()}`,
  };
  const processed = applyRulesToTransactions([newTx], rules.value, savingsGoals.value)[0];

  void runSave(
    async () => {
      await saveTransaction(processed);
      transactions.value = [processed, ...transactions.value];
      bankAccounts.value = bankAccounts.value.map((acc) =>
        acc.iban === newTx.accountIban || acc.type === "checking"
          ? {
              ...acc,
              balance: acc.balance + newTx.amount,
              availableBalance: acc.availableBalance + newTx.amount,
            }
          : acc
      );
    },
    {
      title: "Transactie toegevoegd",
      detail: `${processed.description} · ${euro(processed.amount)}`,
    },
    "Transactie niet opgeslagen"
  );
}

function handleDeleteTransaction(txId: string) {
  const tx = transactions.value.find((t) => t.id === txId);
  if (!tx) return;

  void runSave(
    async () => {
      await deleteTransactionRecord(txId);
      transactions.value = transactions.value.filter((t) => t.id !== txId);
      bankAccounts.value = bankAccounts.value.map((acc) =>
        acc.iban === tx.accountIban || acc.type === "checking"
          ? {
              ...acc,
              balance: acc.balance - tx.amount,
              availableBalance: acc.availableBalance - tx.amount,
            }
          : acc
      );
    },
    {
      title: "Transactie verwijderd",
      detail: `${tx.description} · ${euro(tx.amount)}`,
      variant: "info",
    },
    "Verwijderen mislukt"
  );
}

function handleBulkUpdateCategory(
  txIds: string[],
  newCategory: BudgetCategoryGroup,
  newBudgetItemId?: string
) {
  const catMatch = categories.value.find((c) => c.name === newCategory);
  const targetType: "Inkomsten" | "Uitgave" | "Sparen" =
    newCategory === "Inkomsten" || catMatch?.type === "inkomsten"
      ? "Inkomsten"
      : newCategory === "Spaargeld" || catMatch?.type === "sparen"
        ? "Sparen"
        : "Uitgave";

  const next = transactions.value.map((tx) =>
    txIds.includes(tx.id)
      ? {
          ...tx,
          categoryGroup: newCategory,
          ...(newBudgetItemId ? { budgetItemId: newBudgetItemId } : {}),
          type: targetType,
        }
      : tx
  );

  void runSave(
    async () => {
      await persistChangedTransactions(transactions.value, next);
      transactions.value = next;
    },
    {
      title: `${txIds.length} ${txIds.length === 1 ? "transactie" : "transacties"} bijgewerkt`,
      detail: newBudgetItemId
        ? `Naar ${newCategory} · ${budgetItemName(newBudgetItemId)}`
        : `Naar ${newCategory}`,
    }
  );
}

function handleLinkTransaction(
  txId: string,
  categoryGroup: BudgetCategoryGroup,
  budgetItemId: string,
  createRule?: {
    name: string;
    keyword: string;
    matchField: "description" | "counterparty" | "both";
    targetType: BudgetType;
  }
) {
  const catMatch = categories.value.find((c) => c.name === categoryGroup);
  const targetType: "Inkomsten" | "Uitgave" | "Sparen" =
    categoryGroup === "Inkomsten" || catMatch?.type === "inkomsten"
      ? "Inkomsten"
      : categoryGroup === "Spaargeld" || catMatch?.type === "sparen"
        ? "Sparen"
        : "Uitgave";

  let createdRule: Rule | null = null;
  if (createRule && createRule.keyword.trim()) {
    createdRule = {
      id: `rule-${Date.now()}`,
      name: createRule.name.trim() || `Regel: ${createRule.keyword.trim()}`,
      keyword: createRule.keyword.trim(),
      matchField: createRule.matchField,
      targetGroup: categoryGroup,
      targetBudgetItemId: budgetItemId,
      targetType: createRule.targetType,
      isActive: true,
      matchedCount: 1,
    };
  }

  const next = transactions.value.map((tx) => {
    if (tx.id === txId) {
      return {
        ...tx,
        categoryGroup,
        budgetItemId,
        type: targetType,
        matchedRuleId: createdRule?.id ?? tx.matchedRuleId,
        linkExcluded: false,
        linkExclusionReason: undefined,
      };
    }

    if (
      !createdRule ||
      !isUnlinkedTransaction(tx) ||
      !transactionMatchesRule(tx, createdRule)
    ) {
      return tx;
    }

    return {
      ...tx,
      categoryGroup,
      budgetItemId,
      type: targetType,
      matchedRuleId: createdRule.id,
      linkExcluded: false,
      linkExclusionReason: undefined,
    };
  });

  const extraLinked = createdRule
    ? matchingUnlinkedTransactions(
        transactions.value,
        createdRule.keyword,
        createdRule.matchField,
        txId,
        createdRule.targetType
      ).length
    : 0;

  void runSave(
    async () => {
      if (createdRule) {
        await saveRule(createdRule);
      }
      await persistChangedTransactions(transactions.value, next);
      if (createdRule) {
        rules.value = [...rules.value, createdRule];
      }
      transactions.value = next;
    },
    {
      title:
        extraLinked > 0
          ? `${extraLinked + 1} rijen gekoppeld aan ${budgetItemName(budgetItemId)}`
          : `Gekoppeld aan ${budgetItemName(budgetItemId)}`,
      detail: createdRule
        ? extraLinked > 0
          ? `Regel "${createdRule.keyword}" · ${extraLinked} extra ongekoppelde ${extraLinked === 1 ? "rij" : "rijen"}`
          : `Rubriek ${categoryGroup} · koppelregel "${createdRule.keyword}" aangemaakt`
        : `Rubriek ${categoryGroup}`,
    }
  );
}

function handleCreateRuleFromTransaction(
  keyword: string,
  targetGroup: BudgetCategoryGroup,
  targetType: "inkomsten" | "uitgaven" | "sparen",
  budgetItemId?: string
) {
  editingRule.value = null;
  initialRuleKeyword.value = keyword;
  initialRuleGroup.value = targetGroup;
  initialRuleBudgetItemId.value = budgetItemId || "";
  isAddRuleModalOpen.value = true;
}

function handleSaveRule(ruleData: Omit<Rule, "matchedCount">) {
  const existing = rules.value.find((rule) => rule.id === ruleData.id);
  const savedRule: Rule = {
    ...ruleData,
    matchedCount: existing?.matchedCount ?? 0,
  };
  const extraLinked = matchingUnlinkedTransactions(
    transactions.value,
    savedRule.keyword,
    savedRule.matchField,
    undefined,
    savedRule.targetType
  ).length;
  const updatedRules = existing
    ? rules.value.map((rule) => (rule.id === savedRule.id ? savedRule : rule))
    : [...rules.value, savedRule];
  const nextTxs = applyRulesToTransactions(transactions.value, updatedRules, savingsGoals.value);

  void runSave(
    async () => {
      await saveRule(savedRule);
      await persistChangedTransactions(transactions.value, nextTxs);
      rules.value = updatedRules;
      transactions.value = nextTxs;
    },
    {
      title: existing
        ? "Koppelregel bijgewerkt"
        : extraLinked > 0
          ? `Regel aangemaakt · ${extraLinked} ${extraLinked === 1 ? "rij gekoppeld" : "rijen gekoppeld"}`
          : "Koppelregel aangemaakt",
      detail: `"${savedRule.keyword}" → ${savedRule.targetGroup}`,
    }
  );
}

function handleToggleRule(ruleId: string, active?: boolean) {
  const updated = rules.value.map((r) =>
    r.id === ruleId ? { ...r, isActive: active ?? !r.isActive } : r
  );
  const toggled = updated.find((r) => r.id === ruleId);
  const nextTxs = applyRulesToTransactions(transactions.value, updated, savingsGoals.value);

  void runSave(
    async () => {
      if (toggled) {
        await saveRule(toggled);
      }
      await persistChangedTransactions(transactions.value, nextTxs);
      rules.value = updated;
      transactions.value = nextTxs;
    },
    toggled
      ? {
          title: `Regel ${toggled.isActive ? "geactiveerd" : "gepauzeerd"}`,
          detail: toggled.name,
          variant: "info",
        }
      : undefined
  );
}

function handleDeleteRule(ruleId: string) {
  const removed = rules.value.find((r) => r.id === ruleId);
  const updated = rules.value.filter((r) => r.id !== ruleId);
  const nextTxs = applyRulesToTransactions(transactions.value, updated, savingsGoals.value);

  void runSave(
    async () => {
      await deleteRuleRecord(ruleId);
      await persistChangedTransactions(transactions.value, nextTxs);
      rules.value = updated;
      transactions.value = nextTxs;
    },
    { title: "Koppelregel verwijderd", detail: removed?.name, variant: "info" },
    "Verwijderen mislukt"
  );
}

function handleApplyRulesToAll() {
  const nextTxs = applyRulesToTransactions(transactions.value, rules.value, savingsGoals.value);

  void runSave(
    async () => {
      await persistChangedTransactions(transactions.value, nextTxs);
      transactions.value = nextTxs;
    },
    {
      title: "Regels toegepast op alle transacties",
      detail: `${rules.value.filter((r) => r.isActive).length} actieve regels doorgevoerd`,
    }
  );
}

function transactionTypeFromBudgetType(targetType: BudgetType): Transaction["type"] {
  if (targetType === "inkomsten") {
    return "Inkomsten";
  }
  if (targetType === "sparen") {
    return "Sparen";
  }
  return "Uitgave";
}

function handleAutoProcessSave(assignments: AutoProcessSaveAssignment[]) {
  if (assignments.length === 0) {
    return;
  }

  const assignmentByTransactionId = new Map(
    assignments.map((assignment) => [assignment.transactionId, assignment])
  );

  const createdRules: Rule[] = [];
  const ruleKeyToRule = new Map<string, Rule>();

  for (const assignment of assignments) {
    if (!assignment.createRule || !assignment.createRule.keyword.trim()) {
      continue;
    }

    const dedupeKey = `${assignment.createRule.keyword.trim().toLowerCase()}::${assignment.categoryGroup}::${assignment.budgetItemId}`;
    if (ruleKeyToRule.has(dedupeKey)) {
      continue;
    }

    const rule: Rule = {
      id: `rule-${Date.now()}-${createdRules.length}`,
      name: assignment.createRule.name.trim() || `Regel: ${assignment.createRule.keyword.trim()}`,
      keyword: assignment.createRule.keyword.trim(),
      matchField: assignment.createRule.matchField,
      targetGroup: assignment.categoryGroup,
      targetBudgetItemId: assignment.budgetItemId,
      targetType: assignment.createRule.targetType,
      isActive: true,
      matchedCount: assignments.filter(
        (entry) =>
          entry.createRule &&
          entry.createRule.keyword.trim().toLowerCase() ===
            assignment.createRule!.keyword.trim().toLowerCase() &&
          entry.categoryGroup === assignment.categoryGroup &&
          entry.budgetItemId === assignment.budgetItemId
      ).length,
    };

    createdRules.push(rule);
    ruleKeyToRule.set(dedupeKey, rule);
  }

  const next = transactions.value.map((tx) => {
    const assignment = assignmentByTransactionId.get(tx.id);
    if (!assignment) {
      return tx;
    }

    const dedupeKey = assignment.createRule
      ? `${assignment.createRule.keyword.trim().toLowerCase()}::${assignment.categoryGroup}::${assignment.budgetItemId}`
      : "";
    const createdRule = dedupeKey ? ruleKeyToRule.get(dedupeKey) : undefined;

    return {
      ...tx,
      categoryGroup: assignment.categoryGroup,
      budgetItemId: assignment.budgetItemId,
      type: transactionTypeFromBudgetType(assignment.targetType),
      matchedRuleId: createdRule?.id ?? tx.matchedRuleId,
    };
  });

  void runSave(
    async () => {
      for (const rule of createdRules) {
        await saveRule(rule);
      }
      await persistChangedTransactions(transactions.value, next);
      if (createdRules.length > 0) {
        rules.value = [...rules.value, ...createdRules];
      }
      transactions.value = next;
    },
    {
      title: `${assignments.length} ${assignments.length === 1 ? "transactie" : "transacties"} gekoppeld`,
      detail:
        createdRules.length > 0
          ? `${createdRules.length} nieuwe koppelregels aangemaakt`
          : "Geselecteerde suggesties opgeslagen",
    }
  );
}

function retargetLinksForBudgetItem(
  itemId: string,
  group: BudgetCategoryGroup,
  type: BudgetType
) {
  const txType = transactionTypeFromBudgetType(type);
  const previousTxs = transactions.value;
  const nextTxs = previousTxs.map((tx) =>
    tx.budgetItemId === itemId ? { ...tx, categoryGroup: group, type: txType } : tx
  );
  const nextRules = rules.value.map((rule) =>
    rule.targetBudgetItemId === itemId
      ? { ...rule, targetGroup: group, targetType: type }
      : rule
  );
  const changedRules = nextRules.filter((rule, index) => rule !== rules.value[index]);

  transactions.value = nextTxs;
  rules.value = nextRules;
  void persistChangedTransactions(previousTxs, nextTxs);
  for (const rule of changedRules) {
    void saveRule(rule);
  }
}

function handleUpdateBudgetItem(itemId: string, updates: Partial<BudgetItem>) {
  const current = currentMonth.value.items.find((item) => item.id === itemId);
  const next = { ...current, ...updates } as BudgetItem;
  const amount = next.estimated ?? next.actual ?? 0;
  const identityUpdate: Partial<BudgetItem> = {};
  if (updates.name !== undefined) identityUpdate.name = updates.name;
  if (updates.group !== undefined) identityUpdate.group = updates.group;
  if (updates.type !== undefined) identityUpdate.type = updates.type;
  if (updates.notes !== undefined) identityUpdate.notes = updates.notes;

  baseMonthlyBudgets.value = baseMonthlyBudgets.value.map((m) => ({
    ...m,
    items: m.items.map((item) => {
      if (item.id !== itemId) {
        return item;
      }
      if (m.monthId === selectedMonthId.value) {
        return { ...item, ...updates };
      }
      return Object.keys(identityUpdate).length > 0 ? { ...item, ...identityUpdate } : item;
    }),
  }));

  const moved =
    (updates.group !== undefined && current?.group !== undefined && updates.group !== current.group) ||
    (updates.type !== undefined && current?.type !== undefined && updates.type !== current.type);

  if (moved) {
    retargetLinksForBudgetItem(itemId, next.group, next.type);
  }

  void persistBudgetChange(
    itemId,
    {
      name: next.name,
      group: next.group,
      estimated: amount,
      notes: next.notes ?? null,
      monthId: selectedMonthId.value,
      year: currentMonth.value.year,
    },
    moved ? "Post verplaatst" : "",
    moved ? `${next.name} → ${next.group}` : undefined
  );
}

function handleSaveBudgetItemDetails(
  itemId: string,
  updatedData: {
    name: string;
    group: BudgetCategoryGroup;
    type?: "inkomsten" | "uitgaven" | "sparen";
    monthlyAmounts: Record<string, number>;
    monthlyEntries?: Record<string, { id: string; description: string; amount: number }[]>;
  }
) {
  const existing = currentMonth.value.items.find((item) => item.id === itemId);
  const nextType = updatedData.type || existing?.type || "uitgaven";
  const moved =
    Boolean(existing) &&
    (existing!.group !== updatedData.group || existing!.type !== nextType);

  baseMonthlyBudgets.value = baseMonthlyBudgets.value.map((m) => {
    const monthAmount = updatedData.monthlyAmounts[m.monthId];
    const monthEntries = updatedData.monthlyEntries?.[m.monthId];
    return {
      ...m,
      items: m.items.map((item) => {
        if (item.id === itemId) {
          const newAmount = monthAmount !== undefined ? monthAmount : item.actual;
          return {
            ...item,
            name: updatedData.name,
            group: updatedData.group,
            type: updatedData.type || item.type,
            estimated: newAmount,
            actual: newAmount,
            monthEntries: monthEntries !== undefined ? monthEntries : item.monthEntries,
          };
        }
        return item;
      }),
    };
  });

  if (moved) {
    retargetLinksForBudgetItem(itemId, updatedData.group, nextType);
  }

  void persistBudgetChange(
    itemId,
    {
      name: updatedData.name,
      group: updatedData.group,
      estimated: updatedData.monthlyAmounts[selectedMonthId.value] ?? 0,
      year: currentMonth.value.year,
      monthlyAmounts: updatedData.monthlyAmounts,
      monthlyEntries: updatedData.monthlyEntries,
    },
    "Begrotingspost opgeslagen",
    `${updatedData.name} · ${updatedData.group}`
  );
}

async function handleDeleteBudgetItem(itemId: string) {
  const name = budgetItemName(itemId);
  saveState.value = "saving";
  try {
    await deleteBudgetItem(itemId);
    baseMonthlyBudgets.value = baseMonthlyBudgets.value.map((m) => ({
      ...m,
      items: m.items.filter((item) => item.id !== itemId),
    }));
    transactions.value = transactions.value.map((tx) =>
      tx.budgetItemId === itemId ? { ...tx, budgetItemId: undefined } : tx
    );
    saveState.value = "saved";
    notify("success", "Begrotingspost verwijderd", name);
  } catch {
    saveState.value = "error";
    notify("error", "Verwijderen mislukt", name);
    throw new Error("Verwijderen mislukt");
  }
}

function handleOpenEditBudgetItem(item: BudgetItem) {
  editingBudgetItem.value = item;
}

function handleAddBudgetItem(itemData: Omit<BudgetItem, "id">) {
  const id = `item-${Date.now()}`;
  const newItem: BudgetItem = { ...itemData, id };
  baseMonthlyBudgets.value = baseMonthlyBudgets.value.map((m) =>
    m.monthId === selectedMonthId.value
      ? {
          ...m,
          items: [...m.items, newItem],
        }
      : m
  );
  void persistBudgetChange(
    id,
    {
      name: newItem.name,
      group: newItem.group,
      estimated: newItem.estimated ?? newItem.actual ?? 0,
      notes: newItem.notes ?? null,
      monthId: selectedMonthId.value,
      year: currentMonth.value.year,
    },
    "Begrotingspost toegevoegd",
    `${newItem.name} · ${newItem.group}`
  );
}

function handleSaveCategory(cat: CategoryDefinition) {
  const isUpdate = categories.value.some((c) => c.id === cat.id);

  void runSave(
    async () => {
      await saveCategoryRecord(cat);
      const idx = categories.value.findIndex((c) => c.id === cat.id);
      if (idx >= 0) {
        const oldCat = categories.value[idx];
        if (oldCat.name !== cat.name || oldCat.type !== cat.type) {
          baseMonthlyBudgets.value = baseMonthlyBudgets.value.map((m) => ({
            ...m,
            items: m.items.map((i) =>
              i.group === oldCat.name ? { ...i, group: cat.name, type: cat.type } : i
            ),
          }));
        }
        const copy = [...categories.value];
        copy[idx] = cat;
        categories.value = copy;
      } else {
        categories.value = [...categories.value, cat];
      }
    },
    {
      title: isUpdate ? "Rubriek bijgewerkt" : "Rubriek aangemaakt",
      detail: cat.name,
    }
  );
}

function handleDeleteCategory(catId: string) {
  const catToDelete = categories.value.find((c) => c.id === catId);
  if (!catToDelete) return;

  void runSave(
    async () => {
      await deleteCategoryRecord(catId);
      baseMonthlyBudgets.value = baseMonthlyBudgets.value.map((m) => ({
        ...m,
        items: m.items.map((i) =>
          i.group === catToDelete.name ? { ...i, group: "Overige Kosten", type: "uitgaven" } : i
        ),
      }));
      categories.value = categories.value.filter((c) => c.id !== catId);
    },
    {
      title: "Rubriek verwijderd",
      detail: `Posten van ${catToDelete.name} staan nu onder Overige Kosten`,
      variant: "info",
    },
    "Verwijderen mislukt"
  );
}

function handleOpenAddCategory() {
  editingCategory.value = null;
  isCategoryModalOpen.value = true;
}

function handleOpenEditCategory(cat: CategoryDefinition) {
  editingCategory.value = cat;
  isCategoryModalOpen.value = true;
}

function handleOpenAddBudgetItemModal(defaultGroup?: BudgetCategoryGroup) {
  addBudgetItemDefaultGroup.value = defaultGroup;
  isAddBudgetItemModalOpen.value = true;
}

function handleSaveSavingsGoal(goalData: Omit<SavingsGoal, "id">, editId?: string) {
  const savedGoal: SavingsGoal = {
    ...goalData,
    id: editId || `goal-${Date.now()}`,
  };
  const nextGoals = editId
    ? savingsGoals.value.map((goal) => (goal.id === editId ? savedGoal : goal))
    : [...savingsGoals.value, savedGoal];
  const nextTxs = applyRulesToTransactions(transactions.value, rules.value, nextGoals);

  void runSave(
    async () => {
      await saveSavingsGoalRecord(savedGoal);
      await persistChangedTransactions(transactions.value, nextTxs);
      savingsGoals.value = nextGoals;
      transactions.value = nextTxs;
    },
    {
      title: editId ? "Spaardoel bijgewerkt" : "Spaardoel aangemaakt",
      detail: goalData.name,
    }
  );
}

function handleDeleteSavingsGoal(goalId: string) {
  const removed = savingsGoals.value.find((g) => g.id === goalId);

  void runSave(
    async () => {
      await deleteSavingsGoalRecord(goalId);
      savingsGoals.value = savingsGoals.value.filter((g) => g.id !== goalId);
    },
    { title: "Spaardoel verwijderd", detail: removed?.name, variant: "info" },
    "Verwijderen mislukt"
  );
}

function handleOpenEditSavingsGoal(goal: SavingsGoal) {
  editingSavingsGoal.value = goal;
  isAddSavingsGoalModalOpen.value = true;
}

function handleOpenAddSavingsGoal() {
  editingSavingsGoal.value = null;
  isAddSavingsGoalModalOpen.value = true;
}

function handleUpdateSavingsRow(monthId: string, updates: Partial<SavingsRow>) {
  savingsHistory.value = savingsHistory.value.map((r) =>
    r.monthId === monthId ? { ...r, ...updates } : r
  );
}

function handleUnlinkTransaction(txId: string) {
  const tx = transactions.value.find((t) => t.id === txId);
  if (!tx) {
    return;
  }
  const fromName = tx.budgetItemId ? budgetItemName(tx.budgetItemId) : null;
  const next: Transaction = {
    ...tx,
    budgetItemId: undefined,
    matchedRuleId: undefined,
    linkExcluded: true,
    linkExclusionReason: fromName
      ? `Handmatig ontkoppeld van ${fromName}`
      : "Handmatig ontkoppeld",
  };
  void runSave(
    async () => {
      await saveTransaction(next);
      transactions.value = transactions.value.map((item) => (item.id === txId ? next : item));
    },
    {
      title: "Transactie ontkoppeld",
      detail: `${tx.description} · ${euro(tx.amount)}`,
      variant: "info",
    }
  );
}

function handleSelectMonth(monthId: string) {
  selectedMonthId.value = monthId;
  const month = monthlyBudgets.value.find((entry) => entry.monthId === monthId);
  if (month) {
    selectedYear.value = month.year;
  }
}

function handleYearOverviewSelectMonth(mId: string) {
  handleSelectMonth(mId);
  setActiveTab("maandbegroting");
}

function openUnlinkedInbox() {
  transactionsOpenFilter.value = "UNLINKED";
  setActiveTab("transacties");
}

watch(activeTab, (tab) => {
  if (tab !== "transacties") {
    transactionsOpenFilter.value = "ALL";
  }
});

function openAddRuleModal(keyword = "") {
  editingRule.value = null;
  initialRuleKeyword.value = typeof keyword === "string" ? keyword : "";
  initialRuleGroup.value = "Dagelijks Leven";
  initialRuleBudgetItemId.value = "";
  isAddRuleModalOpen.value = true;
}

function openEditRuleModal(rule: Rule) {
  editingRule.value = rule;
  isAddRuleModalOpen.value = true;
}

function closeRuleModal() {
  isAddRuleModalOpen.value = false;
  editingRule.value = null;
}

function closeCategoryModal() {
  isCategoryModalOpen.value = false;
  editingCategory.value = null;
}

function closeSavingsGoalModal() {
  isAddSavingsGoalModalOpen.value = false;
  editingSavingsGoal.value = null;
}
</script>

<template>
  <div
    v-if="!isReady"
    class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center font-sans"
  >
    <p class="text-sm text-slate-400">Financiën laden…</p>
  </div>

  <div
    v-else
    id="app-root"
    :data-page="activeTab"
    class="min-h-screen bg-slate-950 text-slate-100 flex font-sans antialiased"
  >
    <Sidebar
      :active-tab="activeTab"
      :set-active-tab="setActiveTab"
      :bank-account="primaryBankAccount"
      :is-syncing="isSyncing"
      :is-mobile-open="isMobileDrawerOpen"
      @sync="handleBankSync"
      @close-mobile="isMobileDrawerOpen = false"
    />

    <div class="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen">
      <Header
        :current-month="currentMonth"
        :all-months="monthlyBudgets"
        :reporting-period-label="currentReportingPeriodLabel"
        :bank-account="primaryBankAccount"
        :is-syncing="isSyncing"
        :save-state="saveState"
        :on-open-mobile-menu="() => (isMobileDrawerOpen = true)"
        @select-month="handleSelectMonth"
        @sync="handleBankSync"
        @open-add-transaction="isAddTxModalOpen = true"
        @open-add-budget-item="handleOpenAddBudgetItemModal()"
      />

      <main
        class="flex-1 px-3 md:px-4 py-3 md:py-4 pb-24 md:pb-6 w-full space-y-5"
        :data-page="activeTab"
      >
        <PotCompensationBanner
          :needs="potCompensationNeeds"
          :month-name="currentMonth.monthName"
          @select="openPotFromCompensation"
        />
        <DashboardView
          v-if="activeTab === 'dashboard'"
          :current-month="currentMonth"
          :all-months="monthlyBudgets"
          :transactions="transactions"
          :bank-account="primaryBankAccount"
          :savings-goals="savingsGoals"
          :on-navigate-tab="setActiveTab"
          :on-open-unlinked="openUnlinkedInbox"
        />

        <BudgetSpreadsheetView
          v-if="activeTab === 'maandbegroting'"
          :current-month="currentMonth"
          :all-months="monthlyBudgets"
          :transactions="transactions"
          :bank-account="primaryBankAccount"
          :savings-goals="savingsGoals"
          :on-update-budget-item="handleUpdateBudgetItem"
          :on-open-add-budget-item="() => handleOpenAddBudgetItemModal()"
          :on-open-edit-budget-item="handleOpenEditBudgetItem"
          :on-open-item-transactions="(item) => (itemTransactionsModalItem = item)"
          :on-navigate-tab="setActiveTab"
        />

        <ExpensesView
          v-if="activeTab === 'uitgaven'"
          :current-month="currentMonth"
          :all-months="monthlyBudgets"
          :transactions="transactions"
          :categories="categories"
          :savings-goals="savingsGoals"
          :on-update-budget-item="handleUpdateBudgetItem"
          :on-open-add-budget-item="() => handleOpenAddBudgetItemModal()"
          :on-open-edit-budget-item="handleOpenEditBudgetItem"
          :on-open-item-transactions="(item) => (itemTransactionsModalItem = item)"
        />

        <IncomeView
          v-if="activeTab === 'inkomsten'"
          :current-month="currentMonth"
          :all-months="monthlyBudgets"
          :transactions="transactions"
          :on-update-budget-item="handleUpdateBudgetItem"
          :on-open-add-budget-item="() => handleOpenAddBudgetItemModal('Inkomsten')"
          :on-open-edit-budget-item="handleOpenEditBudgetItem"
          :on-open-item-transactions="(item) => (itemTransactionsModalItem = item)"
        />

        <SavingsTrackerView
          v-if="activeTab === 'sparen'"
          :savings-history="savingsHistory"
          :savings-items="currentMonth.items.filter((i) => i.type === 'sparen')"
          :savings-goals="savingsGoals"
          :transactions="transactions"
          :current-month="currentMonth"
          :all-months="monthlyBudgets"
          :on-open-add-goal="handleOpenAddSavingsGoal"
          :on-edit-goal="handleOpenEditSavingsGoal"
          :on-delete-goal="handleDeleteSavingsGoal"
          :on-update-savings-row="handleUpdateSavingsRow"
          :on-navigate-tab="setActiveTab"
        />

        <TransactionsView
          v-if="activeTab === 'transacties'"
          :transactions="transactions"
          :on-add-transaction="() => (isAddTxModalOpen = true)"
          :on-open-auto-process="() => (isAutoProcessModalOpen = true)"
          :on-delete-transaction="handleDeleteTransaction"
          :on-link-transaction="handleLinkTransaction"
          :on-create-rule-from-transaction="handleCreateRuleFromTransaction"
          :on-bulk-update-category="handleBulkUpdateCategory"
          :rules="rules"
          :budget-items="currentMonth.items"
          :categories="categories"
          :on-open-add-budget-item-modal="handleOpenAddBudgetItemModal"
          :initial-filter="transactionsOpenFilter"
        />

        <EnableBankingView
          v-if="activeTab === 'enablebanking'"
          :bank-account="primaryBankAccount"
          :savings-goals="savingsGoals"
          :current-month="currentMonth"
          :transactions="transactions"
          :on-sync="handleBankSync"
          :is-syncing="isSyncing"
          :on-open-add-goal="handleOpenAddSavingsGoal"
        />

        <CategoriesView
          v-if="activeTab === 'categorieen'"
          :current-month="currentMonth"
          :categories="categories"
          :on-open-add-budget-item="(grp) => handleOpenAddBudgetItemModal(grp)"
          :on-open-edit-budget-item="handleOpenEditBudgetItem"
          :on-open-add-category="handleOpenAddCategory"
          :on-open-edit-category="handleOpenEditCategory"
          :on-delete-category="handleDeleteCategory"
          :on-update-budget-item="handleUpdateBudgetItem"
          :on-delete-budget-item="handleDeleteBudgetItem"
        />

        <KoppelregelsView
          v-if="activeTab === 'koppelregels'"
          :rules="rules"
          :on-add-rule="openAddRuleModal"
          :on-edit-rule="openEditRuleModal"
          :on-toggle-rule="handleToggleRule"
          :on-delete-rule="handleDeleteRule"
          :on-apply-rules-to-all="handleApplyRulesToAll"
          :transactions="transactions"
          :budget-items="currentMonth.items"
        />

        <YearOverviewView
          v-if="activeTab === 'jaaroverzicht'"
          :all-months="monthlyBudgets"
          :transactions="transactions"
          :savings-goals="savingsGoals"
          :bank-balance="primaryBankAccount.balance"
          :on-select-month="handleYearOverviewSelectMonth"
        />

        <SettingsView v-if="activeTab === 'settings'" />
      </main>
    </div>

    <BottomNav
      :active-tab="activeTab"
      :set-active-tab="setActiveTab"
      @open-mobile-menu="isMobileDrawerOpen = true"
    />

    <AutoProcessTransactionsModal
      :is-open="isAutoProcessModalOpen"
      :on-close="() => (isAutoProcessModalOpen = false)"
      :transactions="transactions"
      :rules="rules"
      :savings-goals="savingsGoals"
      :budget-items="currentMonth.items"
      :categories="categories"
      :bank-accounts="bankAccounts"
      :current-month="currentMonth"
      :on-save="handleAutoProcessSave"
    />

    <AddTransactionModal
      :is-open="isAddTxModalOpen"
      :on-close="() => (isAddTxModalOpen = false)"
      :on-add="handleAddTransaction"
    />

    <AddBudgetItemModal
      :is-open="isAddBudgetItemModalOpen"
      :on-close="() => (isAddBudgetItemModalOpen = false)"
      :on-add="handleAddBudgetItem"
      :categories="categories"
      :default-group="addBudgetItemDefaultGroup"
    />

    <EditBudgetItemModal
      :is-open="!!editingBudgetItem"
      :on-close="() => (editingBudgetItem = null)"
      :item="editingBudgetItem"
      :current-month-id="selectedMonthId"
      :all-months="monthlyBudgets"
      :categories="categories"
      :on-save="handleSaveBudgetItemDetails"
      :on-delete="handleDeleteBudgetItem"
    />

    <ManageCategoryModal
      :is-open="isCategoryModalOpen"
      :on-close="closeCategoryModal"
      :category="editingCategory"
      :on-save="handleSaveCategory"
      :on-delete="handleDeleteCategory"
    />

    <AddRuleModal
      :is-open="isAddRuleModalOpen"
      :on-close="closeRuleModal"
      :on-save="handleSaveRule"
      :editing-rule="editingRule"
      :initial-keyword="initialRuleKeyword"
      :initial-group="initialRuleGroup"
      :initial-budget-item-id="initialRuleBudgetItemId"
      :budget-items="currentMonth.items"
      :categories="categories"
      :transactions="transactions"
      :current-month="currentMonth"
      :all-months="monthlyBudgets"
    />

    <AddSavingsGoalModal
      :is-open="isAddSavingsGoalModalOpen"
      :on-close="closeSavingsGoalModal"
      :on-save="handleSaveSavingsGoal"
      :editing-goal="editingSavingsGoal"
      :transactions="transactions"
      :own-ibans="bankAccounts.map((account) => account.iban).filter(Boolean)"
      :budget-items="currentMonth.items"
    />

    <ItemTransactionsModal
      :is-open="!!itemTransactionsModalItem"
      :on-close="() => (itemTransactionsModalItem = null)"
      :budget-item="itemTransactionsModalItem"
      :current-month="currentMonth"
      :all-months="monthlyBudgets"
      :transactions="transactions"
      :savings-goals="savingsGoals"
      :own-ibans="bankAccounts.map((account) => account.iban).filter(Boolean)"
      :on-unlink-transaction="handleUnlinkTransaction"
      :on-link-transaction="(txId, group, itemId) => handleLinkTransaction(txId, group, itemId)"
      :on-open-edit-budget-item="handleOpenEditBudgetItem"
      :on-add-transaction-to-item="() => (isAddTxModalOpen = true)"
      :on-open-pot="openPotFromBudgetItem"
    />

    <PotSettlementModal
      v-if="currentMonth"
      :is-open="potDetailGoal !== null"
      :on-close="() => (potDetailGoal = null)"
      :goal="potDetailGoal"
      :settlement="potDetailSettlement"
      :current-month="currentMonth"
    />

    <ToastStack :toasts="toasts" @dismiss="dismiss" />
  </div>
</template>
