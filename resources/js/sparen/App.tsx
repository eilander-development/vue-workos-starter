import React, { useEffect, useMemo, useState } from "react";
import {
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
} from "./types";
import {
  INITIAL_MONTHLY_BUDGETS,
  INITIAL_RULES,
  INITIAL_SAVINGS_HISTORY,
  INITIAL_SAVINGS_GOALS,
  DEFAULT_CATEGORY_DEFINITIONS,
} from "./data/mockBudgetData";
import { Sidebar } from "./components/Sidebar";
import { Header } from "./components/Header";
import { BottomNav } from "./components/BottomNav";
import { DashboardView } from "./components/DashboardView";
import { BudgetSpreadsheetView } from "./components/BudgetSpreadsheetView";
import { ExpensesView } from "./components/ExpensesView";
import { IncomeView } from "./components/IncomeView";
import { SavingsTrackerView } from "./components/SavingsTrackerView";
import { TransactionsView } from "./components/TransactionsView";
import { EnableBankingView } from "./components/EnableBankingView";
import { CategoriesView } from "./components/CategoriesView";
import { KoppelregelsView } from "./components/KoppelregelsView";
import { YearOverviewView } from "./components/YearOverviewView";
import { AddTransactionModal } from "./components/AddTransactionModal";
import { AddBudgetItemModal } from "./components/AddBudgetItemModal";
import { EditBudgetItemModal } from "./components/EditBudgetItemModal";
import { ManageCategoryModal } from "./components/ManageCategoryModal";
import { AddRuleModal } from "./components/AddRuleModal";
import { AddSavingsGoalModal } from "./components/AddSavingsGoalModal";
import { ItemTransactionsModal } from "./components/ItemTransactionsModal";
import { ToastStack, useToasts } from "./components/Toast";
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
import { monthDatePrefix } from "./month";
import { isUnlinkedTransaction, matchingUnlinkedTransactions, transactionMatchesKeyword } from "./matchRule";
import { transactionMatchesSavingsGoal } from "./matchSavings";

export type SaveState = "idle" | "saving" | "saved" | "error";

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
};

export function App() {
  const [activeTab, setActiveTab] = useSparenRoute();
  const [selectedMonthId, setSelectedMonthId] = useState<string>("aug");
  const [baseMonthlyBudgets, setBaseMonthlyBudgets] = useState<MonthlyBudget[]>(INITIAL_MONTHLY_BUDGETS);
  const [categories, setCategories] = useState<CategoryDefinition[]>(DEFAULT_CATEGORY_DEFINITIONS);
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [rules, setRules] = useState<Rule[]>(INITIAL_RULES);
  const [bankAccounts, setBankAccounts] = useState<BankAccount[]>([]);
  const [savingsGoals, setSavingsGoals] = useState<SavingsGoal[]>(INITIAL_SAVINGS_GOALS);
  const [savingsHistory, setSavingsHistory] = useState<SavingsRow[]>(INITIAL_SAVINGS_HISTORY);
  const [isSyncing, setIsSyncing] = useState<boolean>(false);
  const [isMobileDrawerOpen, setIsMobileDrawerOpen] = useState(false);

  // Modals state
  const [isAddTxModalOpen, setIsAddTxModalOpen] = useState(false);
  const [isAddBudgetItemModalOpen, setIsAddBudgetItemModalOpen] = useState(false);
  const [addBudgetItemDefaultGroup, setAddBudgetItemDefaultGroup] = useState<BudgetCategoryGroup | undefined>(undefined);
  const [editingBudgetItem, setEditingBudgetItem] = useState<BudgetItem | null>(null);
  const [isCategoryModalOpen, setIsCategoryModalOpen] = useState(false);
  const [editingCategory, setEditingCategory] = useState<CategoryDefinition | null>(null);
  const [isAddRuleModalOpen, setIsAddRuleModalOpen] = useState(false);
  const [isAddSavingsGoalModalOpen, setIsAddSavingsGoalModalOpen] = useState(false);
  const [editingSavingsGoal, setEditingSavingsGoal] = useState<SavingsGoal | null>(null);
  const [itemTransactionsModalItem, setItemTransactionsModalItem] = useState<BudgetItem | null>(null);

  const [initialRuleKeyword, setInitialRuleKeyword] = useState<string>("");
  const [initialRuleGroup, setInitialRuleGroup] = useState<BudgetCategoryGroup>("Dagelijks Leven");
  const [initialRuleBudgetItemId, setInitialRuleBudgetItemId] = useState<string>("");
  const [isReady, setIsReady] = useState(false);
  const [saveState, setSaveState] = useState<SaveState>("idle");
  const { toasts, notify, dismiss } = useToasts();

  const applyRemoteState = (data: Record<string, any>) => {
    if (data.categories) setCategories(data.categories);
    if (data.monthlyBudgets) setBaseMonthlyBudgets(data.monthlyBudgets);
    if (data.transactions) setTransactions(data.transactions);
    if (data.rules) setRules(data.rules);
    if (data.bankAccounts) setBankAccounts(data.bankAccounts);
    if (data.savingsGoals) setSavingsGoals(data.savingsGoals);
    if (data.savingsHistory) setSavingsHistory(data.savingsHistory);
  };

  useEffect(() => {
    loadSparenState()
      .then((data) => {
        applyRemoteState(data);
      })
      .catch(() =>
        notify("error", "Gegevens laden mislukt", "De app toont nu voorbeeldcijfers. Herlaad de pagina om opnieuw te proberen.")
      )
      .finally(() => setIsReady(true));
  }, [notify]);

  const persistChangedTransactions = async (previous: Transaction[], next: Transaction[]) => {
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
        before.description !== tx.description ||
        before.amount !== tx.amount ||
        before.date !== tx.date
      );
    });

    await saveTransactions(changed);
  };

  const runSave = async (
    work: () => Promise<unknown>,
    success?: { title: string; detail?: string; variant?: "success" | "info" },
    errorTitle = "Opslaan mislukt"
  ) => {
    setSaveState("saving");
    try {
      await work();
      setSaveState("saved");
      if (success?.title) {
        notify(success.variant ?? "success", success.title, success.detail);
      }
    } catch (error) {
      setSaveState("error");
      notify(
        "error",
        errorTitle,
        error instanceof Error ? error.message : "De laatste wijziging staat nog niet in de database."
      );
      throw error;
    }
  };

  useEffect(() => {
    if (saveState !== "saved") {
      return;
    }
    const timeout = window.setTimeout(() => setSaveState("idle"), 2500);
    return () => window.clearTimeout(timeout);
  }, [saveState]);

  // Apply auto-categorization rules & savings goal mappings to transactions
  const applyRulesToTransactions = (txs: Transaction[], rls: Rule[], sGoals: SavingsGoal[]) => {
    const ownIbans = bankAccounts.map((account) => account.iban).filter(Boolean);

    return txs.map((tx) => {
      for (const goal of sGoals) {
        if (!isUnlinkedTransaction(tx) || !transactionMatchesSavingsGoal(tx, goal, ownIbans)) {
          continue;
        }

        return {
          ...tx,
          type: "Sparen" as const,
          categoryGroup: "Spaargeld" as const,
          budgetItemId: goal.categoryBudgetItemId || tx.budgetItemId || "spaar-1",
          counterparty: goal.bankName || goal.name,
        };
      }

      // 2. Find matching rule from rules table
      const match = rls.find((r) => {
        if (!r.isActive) return false;
        const kw = r.keyword.toLowerCase();
        const inDesc = tx.description.toLowerCase().includes(kw);
        const inCounterparty = tx.counterparty?.toLowerCase().includes(kw) || false;
        if (r.matchField === "description") return inDesc;
        if (r.matchField === "counterparty") return inCounterparty;
        return inDesc || inCounterparty;
      });

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
  };

  // Re-calculate live paid/received from transactions
  const monthlyBudgets = useMemo(() => {
    return baseMonthlyBudgets.map((mb) => {
      const monthPrefix = monthDatePrefix(mb);
      const txsInMonth = transactions.filter((t) => monthPrefix && t.date.startsWith(monthPrefix));

      const updatedItems = mb.items.map((item) => {
        const matchingTxs = txsInMonth.filter((t) => {
          if (t.budgetItemId && t.budgetItemId === item.id) return true;
          if (t.categoryGroup === item.group && !t.budgetItemId) {
            const desc = t.description.toLowerCase();
            const itemNameLower = item.name.toLowerCase();
            if (desc.includes(itemNameLower) || itemNameLower.includes(desc)) return true;
          }
          return false;
        });

        const totalFromTxs = matchingTxs.reduce((sum, t) => sum + Math.abs(t.amount), 0);
        const paymentCount = matchingTxs.length;
        const finalPaid = Math.max(item.paidOrReceived, totalFromTxs);

        return {
          ...item,
          paidOrReceived: finalPaid,
          paymentCount: paymentCount > 0 ? paymentCount : item.paymentCount,
          isPaid: finalPaid >= item.actual && item.actual > 0,
        };
      });

      return {
        ...mb,
        items: updatedItems,
      };
    });
  }, [baseMonthlyBudgets, transactions]);

  const currentMonth = useMemo(() => {
    return monthlyBudgets.find((m) => m.monthId === selectedMonthId) || monthlyBudgets[0];
  }, [monthlyBudgets, selectedMonthId]);

  const primaryBankAccount =
    bankAccounts.find((account) => account.type === "checking") ||
    bankAccounts[0] ||
    DISCONNECTED_CHECKING_ACCOUNT;

  const budgetItemName = (itemId?: string) =>
    monthlyBudgets.flatMap((month) => month.items).find((item) => item.id === itemId)?.name ??
    "begrotingspost";

  const euro = (amount: number) =>
    `€ ${Math.abs(amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 })}`;

  const persistBudgetChange = async (
    itemId: string,
    payload: Record<string, unknown>,
    successTitle: string,
    successDetail?: string
  ) => {
    setSaveState("saving");
    try {
      await saveBudgetItem(itemId, payload);
      setSaveState("saved");
      if (successTitle) {
        notify("success", successTitle, successDetail);
      }
    } catch {
      setSaveState("error");
      notify("error", "Begrotingspost niet opgeslagen", "De wijziging staat nog niet in de database.");
    }
  };

  const handleBankSync = async () => {
    setIsSyncing(true);
    try {
      const result = await syncSparenBank();

      if (result?.needsConnect) {
        notify("info", "Bankkoppeling nodig", "Je wordt doorgestuurd naar ING om opnieuw toegang te geven.");
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
      setIsSyncing(false);
    }
  };

  // Add Transaction
  const handleAddTransaction = (txData: Omit<Transaction, "id">) => {
    const newTx: Transaction = {
      ...txData,
      id: `tx-${Date.now()}`,
    };
    const processed = applyRulesToTransactions([newTx], rules, savingsGoals)[0];

    void runSave(
      async () => {
        await saveTransaction(processed);
        setTransactions((prev) => [processed, ...prev]);
        setBankAccounts((prev) =>
          prev.map((acc) =>
            acc.iban === newTx.accountIban || acc.type === "checking"
              ? {
                  ...acc,
                  balance: acc.balance + newTx.amount,
                  availableBalance: acc.availableBalance + newTx.amount,
                }
              : acc
          )
        );
      },
      { title: "Transactie toegevoegd", detail: `${processed.description} · ${euro(processed.amount)}` },
      "Transactie niet opgeslagen"
    );
  };

  // Delete Transaction
  const handleDeleteTransaction = (txId: string) => {
    const tx = transactions.find((t) => t.id === txId);
    if (!tx) return;

    void runSave(
      async () => {
        await deleteTransactionRecord(txId);
        setTransactions((prev) => prev.filter((t) => t.id !== txId));
        setBankAccounts((prev) =>
          prev.map((acc) =>
            acc.iban === tx.accountIban || acc.type === "checking"
              ? {
                  ...acc,
                  balance: acc.balance - tx.amount,
                  availableBalance: acc.availableBalance - tx.amount,
                }
              : acc
          )
        );
      },
      { title: "Transactie verwijderd", detail: `${tx.description} · ${euro(tx.amount)}`, variant: "info" },
      "Verwijderen mislukt"
    );
  };

  // Bulk update category & post for transactions
  const handleBulkUpdateCategory = (
    txIds: string[],
    newCategory: BudgetCategoryGroup,
    newBudgetItemId?: string
  ) => {
    const catMatch = categories.find((c) => c.name === newCategory);
    const targetType: "Inkomsten" | "Uitgave" | "Sparen" =
      newCategory === "Inkomsten" || catMatch?.type === "inkomsten"
        ? "Inkomsten"
        : newCategory === "Spaargeld" || catMatch?.type === "sparen"
        ? "Sparen"
        : "Uitgave";

    const next = transactions.map((tx) =>
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
        await persistChangedTransactions(transactions, next);
        setTransactions(next);
      },
      {
        title: `${txIds.length} ${txIds.length === 1 ? "transactie" : "transacties"} bijgewerkt`,
        detail: newBudgetItemId ? `Naar ${newCategory} · ${budgetItemName(newBudgetItemId)}` : `Naar ${newCategory}`,
      }
    );
  };

  // Direct link transaction to budget item with option to create automated rule
  const handleLinkTransaction = (
    txId: string,
    categoryGroup: BudgetCategoryGroup,
    budgetItemId: string,
    createRule?: {
      name: string;
      keyword: string;
      matchField: "description" | "counterparty" | "both";
      targetType: BudgetType;
    }
  ) => {
    const catMatch = categories.find((c) => c.name === categoryGroup);
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

    const next = transactions.map((tx) => {
      if (tx.id === txId) {
        return {
          ...tx,
          categoryGroup,
          budgetItemId,
          type: targetType,
          matchedRuleId: createdRule?.id ?? tx.matchedRuleId,
        };
      }

      if (
        !createdRule ||
        !isUnlinkedTransaction(tx) ||
        !transactionMatchesKeyword(tx, createdRule.keyword, createdRule.matchField)
      ) {
        return tx;
      }

      return {
        ...tx,
        categoryGroup,
        budgetItemId,
        type: targetType,
        matchedRuleId: createdRule.id,
      };
    });

    const extraLinked = createdRule
      ? matchingUnlinkedTransactions(transactions, createdRule.keyword, createdRule.matchField, txId).length
      : 0;

    void runSave(
      async () => {
        if (createdRule) {
          await saveRule(createdRule);
        }
        await persistChangedTransactions(transactions, next);
        if (createdRule) {
          setRules((prev) => [...prev, createdRule]);
        }
        setTransactions(next);
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
  };

  // Create rule from transaction
  const handleCreateRuleFromTransaction = (
    keyword: string,
    targetGroup: BudgetCategoryGroup,
    targetType: "inkomsten" | "uitgaven" | "sparen",
    budgetItemId?: string
  ) => {
    setInitialRuleKeyword(keyword);
    setInitialRuleGroup(targetGroup);
    setInitialRuleBudgetItemId(budgetItemId || "");
    setIsAddRuleModalOpen(true);
  };

  // Add rule
  const handleAddRule = (ruleData: Omit<Rule, "id" | "matchedCount">) => {
    const newRule: Rule = {
      ...ruleData,
      id: `rule-${Date.now()}`,
      matchedCount: 0,
    };
    const extraLinked = matchingUnlinkedTransactions(
      transactions,
      newRule.keyword,
      newRule.matchField
    ).length;
    const updatedRules = [...rules, newRule];
    const nextTxs = applyRulesToTransactions(transactions, updatedRules, savingsGoals);

    void runSave(
      async () => {
        await saveRule(newRule);
        await persistChangedTransactions(transactions, nextTxs);
        setRules(updatedRules);
        setTransactions(nextTxs);
      },
      {
        title:
          extraLinked > 0
            ? `Regel aangemaakt · ${extraLinked} ${extraLinked === 1 ? "rij gekoppeld" : "rijen gekoppeld"}`
            : "Koppelregel aangemaakt",
        detail: `"${newRule.keyword}" → ${newRule.targetGroup}`,
      }
    );
  };

  // Toggle rule
  const handleToggleRule = (ruleId: string) => {
    const updated = rules.map((r) => (r.id === ruleId ? { ...r, isActive: !r.isActive } : r));
    const toggled = updated.find((r) => r.id === ruleId);
    const nextTxs = applyRulesToTransactions(transactions, updated, savingsGoals);

    void runSave(
      async () => {
        if (toggled) {
          await saveRule(toggled);
        }
        await persistChangedTransactions(transactions, nextTxs);
        setRules(updated);
        setTransactions(nextTxs);
      },
      toggled
        ? {
            title: `Regel ${toggled.isActive ? "geactiveerd" : "gepauzeerd"}`,
            detail: toggled.name,
            variant: "info",
          }
        : undefined
    );
  };

  // Delete rule
  const handleDeleteRule = (ruleId: string) => {
    const removed = rules.find((r) => r.id === ruleId);
    const updated = rules.filter((r) => r.id !== ruleId);
    const nextTxs = applyRulesToTransactions(transactions, updated, savingsGoals);

    void runSave(
      async () => {
        await deleteRuleRecord(ruleId);
        await persistChangedTransactions(transactions, nextTxs);
        setRules(updated);
        setTransactions(nextTxs);
      },
      { title: "Koppelregel verwijderd", detail: removed?.name, variant: "info" },
      "Verwijderen mislukt"
    );
  };

  // Apply rules to all past transactions
  const handleApplyRulesToAll = () => {
    const nextTxs = applyRulesToTransactions(transactions, rules, savingsGoals);

    void runSave(
      async () => {
        await persistChangedTransactions(transactions, nextTxs);
        setTransactions(nextTxs);
      },
      {
        title: "Regels toegepast op alle transacties",
        detail: `${rules.filter((r) => r.isActive).length} actieve regels doorgevoerd`,
      }
    );
  };

  // Update budget item in active month
  const handleUpdateBudgetItem = (itemId: string, updates: Partial<BudgetItem>) => {
    const current = currentMonth.items.find((item) => item.id === itemId);
    const next = { ...current, ...updates } as BudgetItem;
    const amount = next.estimated ?? next.actual ?? 0;

    setBaseMonthlyBudgets((prev) =>
      prev.map((m) =>
        m.monthId === selectedMonthId
          ? {
              ...m,
              items: m.items.map((item) => (item.id === itemId ? { ...item, ...updates } : item)),
            }
          : m
      )
    );

    void persistBudgetChange(itemId, {
      name: next.name,
      group: next.group,
      estimated: amount,
      notes: next.notes ?? null,
      monthId: selectedMonthId,
      year: currentMonth.year,
    }, "");
  };

  // Comprehensive update from EditBudgetItemModal (across months)
  const handleSaveBudgetItemDetails = (
    itemId: string,
    updatedData: {
      name: string;
      group: BudgetCategoryGroup;
      type?: "inkomsten" | "uitgaven" | "sparen";
      monthlyAmounts: Record<string, number>;
      monthlyEntries?: Record<string, { id: string; description: string; amount: number }[]>;
    }
  ) => {
    setBaseMonthlyBudgets((prev) =>
      prev.map((m) => {
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
      })
    );

    void persistBudgetChange(
      itemId,
      {
        name: updatedData.name,
        group: updatedData.group,
        estimated: updatedData.monthlyAmounts[selectedMonthId] ?? 0,
        year: currentMonth.year,
        monthlyAmounts: updatedData.monthlyAmounts,
        monthlyEntries: updatedData.monthlyEntries,
      },
      "Begrotingspost opgeslagen",
      `${updatedData.name} · ${updatedData.group}`
    );
  };

  // Delete budget item
  const handleDeleteBudgetItem = async (itemId: string) => {
    const name = budgetItemName(itemId);
    setSaveState("saving");
    try {
      await deleteBudgetItem(itemId);
      setBaseMonthlyBudgets((prev) =>
        prev.map((m) => ({
          ...m,
          items: m.items.filter((item) => item.id !== itemId),
        }))
      );
      setTransactions((prev) =>
        prev.map((tx) => (tx.budgetItemId === itemId ? { ...tx, budgetItemId: undefined } : tx))
      );
      setSaveState("saved");
      notify("success", "Begrotingspost verwijderd", name);
    } catch (error) {
      setSaveState("error");
      notify("error", "Verwijderen mislukt", name);
      throw error;
    }
  };

  const handleOpenEditBudgetItem = (item: BudgetItem) => {
    setEditingBudgetItem(item);
  };

  // Add budget line item
  const handleAddBudgetItem = (itemData: Omit<BudgetItem, "id">) => {
    const id = `item-${Date.now()}`;
    const newItem: BudgetItem = { ...itemData, id };
    setBaseMonthlyBudgets((prev) =>
      prev.map((m) =>
        m.monthId === selectedMonthId
          ? {
              ...m,
              items: [...m.items, newItem],
            }
          : m
      )
    );
    void persistBudgetChange(
      id,
      {
        name: newItem.name,
        group: newItem.group,
        estimated: newItem.estimated ?? newItem.actual ?? 0,
        notes: newItem.notes ?? null,
        monthId: selectedMonthId,
        year: currentMonth.year,
      },
      "Begrotingspost toegevoegd",
      `${newItem.name} · ${newItem.group}`
    );
  };

  // Category Management Handlers
  const handleSaveCategory = (cat: CategoryDefinition) => {
    const isUpdate = categories.some((c) => c.id === cat.id);

    void runSave(
      async () => {
        await saveCategoryRecord(cat);
        setCategories((prev) => {
          const idx = prev.findIndex((c) => c.id === cat.id);
          if (idx >= 0) {
            const oldCat = prev[idx];
            if (oldCat.name !== cat.name || oldCat.type !== cat.type) {
              setBaseMonthlyBudgets((mPrev) =>
                mPrev.map((m) => ({
                  ...m,
                  items: m.items.map((i) =>
                    i.group === oldCat.name ? { ...i, group: cat.name, type: cat.type } : i
                  ),
                }))
              );
            }
            const copy = [...prev];
            copy[idx] = cat;
            return copy;
          }

          return [...prev, cat];
        });
      },
      {
        title: isUpdate ? "Rubriek bijgewerkt" : "Rubriek aangemaakt",
        detail: cat.name,
      }
    );
  };

  const handleDeleteCategory = (catId: string) => {
    const catToDelete = categories.find((c) => c.id === catId);
    if (!catToDelete) return;

    void runSave(
      async () => {
        await deleteCategoryRecord(catId);
        setBaseMonthlyBudgets((mPrev) =>
          mPrev.map((m) => ({
            ...m,
            items: m.items.map((i) =>
              i.group === catToDelete.name ? { ...i, group: "Overige Kosten", type: "uitgaven" } : i
            ),
          }))
        );
        setCategories((prev) => prev.filter((c) => c.id !== catId));
      },
      {
        title: "Rubriek verwijderd",
        detail: `Posten van ${catToDelete.name} staan nu onder Overige Kosten`,
        variant: "info",
      },
      "Verwijderen mislukt"
    );
  };

  const handleOpenAddCategory = () => {
    setEditingCategory(null);
    setIsCategoryModalOpen(true);
  };

  const handleOpenEditCategory = (cat: CategoryDefinition) => {
    setEditingCategory(cat);
    setIsCategoryModalOpen(true);
  };

  const handleOpenAddBudgetItemModal = (defaultGroup?: BudgetCategoryGroup) => {
    setAddBudgetItemDefaultGroup(defaultGroup);
    setIsAddBudgetItemModalOpen(true);
  };

  // Savings Goals Management
  const handleSaveSavingsGoal = (goalData: Omit<SavingsGoal, "id">, editId?: string) => {
    const savedGoal: SavingsGoal = {
      ...goalData,
      id: editId || `goal-${Date.now()}`,
    };
    const nextGoals = editId
      ? savingsGoals.map((goal) => (goal.id === editId ? savedGoal : goal))
      : [...savingsGoals, savedGoal];
    const nextTxs = applyRulesToTransactions(transactions, rules, nextGoals);

    void runSave(
      async () => {
        await saveSavingsGoalRecord(savedGoal);
        await persistChangedTransactions(transactions, nextTxs);
        setSavingsGoals(nextGoals);
        setTransactions(nextTxs);
      },
      {
        title: editId ? "Spaardoel bijgewerkt" : "Spaardoel aangemaakt",
        detail: goalData.name,
      }
    );
  };

  const handleDeleteSavingsGoal = (goalId: string) => {
    const removed = savingsGoals.find((g) => g.id === goalId);

    void runSave(
      async () => {
        await deleteSavingsGoalRecord(goalId);
        setSavingsGoals((prev) => prev.filter((g) => g.id !== goalId));
      },
      { title: "Spaardoel verwijderd", detail: removed?.name, variant: "info" },
      "Verwijderen mislukt"
    );
  };

  const handleOpenEditSavingsGoal = (goal: SavingsGoal) => {
    setEditingSavingsGoal(goal);
    setIsAddSavingsGoalModalOpen(true);
  };

  const handleOpenAddSavingsGoal = () => {
    setEditingSavingsGoal(null);
    setIsAddSavingsGoalModalOpen(true);
  };

  if (!isReady) {
    return (
      <div className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center font-sans">
        <p className="text-sm text-slate-400">Financiën laden…</p>
      </div>
    );
  }

  return (
    <div id="app-root" data-page={activeTab} className="min-h-screen bg-slate-950 text-slate-100 flex font-sans antialiased">
      {/* Sidebar Navigation (Desktop & Mobile Drawer) */}
      <Sidebar
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        bankAccount={primaryBankAccount}
        onSync={handleBankSync}
        isSyncing={isSyncing}
        isMobileOpen={isMobileDrawerOpen}
        onCloseMobile={() => setIsMobileDrawerOpen(false)}
      />

      {/* Main Content Area */}
      <div className="flex-1 flex flex-col min-w-0 overflow-y-auto h-screen">
        {/* Sticky Header */}
        <Header
          currentMonth={currentMonth}
          allMonths={monthlyBudgets}
          onSelectMonth={setSelectedMonthId}
          bankAccount={primaryBankAccount}
          onSync={handleBankSync}
          isSyncing={isSyncing}
          onOpenAddTransaction={() => setIsAddTxModalOpen(true)}
          onOpenAddBudgetItem={() => handleOpenAddBudgetItemModal()}
          onOpenMobileMenu={() => setIsMobileDrawerOpen(true)}
          saveState={saveState}
        />

        {/* View Router with mobile bottom padding */}
        <main
          className="flex-1 p-3 sm:p-5 md:p-6 pb-24 md:pb-8 max-w-7xl w-full mx-auto space-y-6"
          data-page={activeTab}
        >
          {activeTab === "dashboard" && (
            <DashboardView
              currentMonth={currentMonth}
              allMonths={monthlyBudgets}
              transactions={transactions}
              bankAccount={primaryBankAccount}
              onNavigateTab={setActiveTab}
            />
          )}

          {activeTab === "maandbegroting" && (
            <BudgetSpreadsheetView
              currentMonth={currentMonth}
              allMonths={monthlyBudgets}
              transactions={transactions}
              bankAccount={primaryBankAccount}
              onSelectMonth={setSelectedMonthId}
              onUpdateBudgetItem={handleUpdateBudgetItem}
              onOpenAddBudgetItem={() => handleOpenAddBudgetItemModal()}
              onOpenEditBudgetItem={handleOpenEditBudgetItem}
              onOpenItemTransactions={(item) => setItemTransactionsModalItem(item)}
              onNavigateTab={setActiveTab}
            />
          )}

          {activeTab === "uitgaven" && (
            <ExpensesView
              currentMonth={currentMonth}
              allMonths={monthlyBudgets}
              transactions={transactions}
              onUpdateBudgetItem={handleUpdateBudgetItem}
              onOpenAddBudgetItem={() => handleOpenAddBudgetItemModal()}
              onOpenEditBudgetItem={handleOpenEditBudgetItem}
              onOpenItemTransactions={(item) => setItemTransactionsModalItem(item)}
            />
          )}

          {activeTab === "inkomsten" && (
            <IncomeView
              currentMonth={currentMonth}
              allMonths={monthlyBudgets}
              transactions={transactions}
              onUpdateBudgetItem={handleUpdateBudgetItem}
              onOpenAddBudgetItem={() => handleOpenAddBudgetItemModal("Inkomsten")}
              onOpenEditBudgetItem={handleOpenEditBudgetItem}
              onOpenItemTransactions={(item) => setItemTransactionsModalItem(item)}
            />
          )}

          {activeTab === "sparen" && (
            <SavingsTrackerView
              savingsHistory={savingsHistory}
              savingsItems={currentMonth.items.filter((i) => i.type === "sparen")}
              savingsGoals={savingsGoals}
              transactions={transactions}
              currentMonth={currentMonth}
              onOpenAddGoal={handleOpenAddSavingsGoal}
              onEditGoal={handleOpenEditSavingsGoal}
              onDeleteGoal={handleDeleteSavingsGoal}
              onUpdateSavingsRow={(mId, updates) => {
                setSavingsHistory((prev) =>
                  prev.map((r) => (r.monthId === mId ? { ...r, ...updates } : r))
                );
              }}
            />
          )}

          {activeTab === "transacties" && (
            <TransactionsView
              transactions={transactions}
              onAddTransaction={() => setIsAddTxModalOpen(true)}
              onDeleteTransaction={handleDeleteTransaction}
              onLinkTransaction={handleLinkTransaction}
              onCreateRuleFromTransaction={handleCreateRuleFromTransaction}
              onBulkUpdateCategory={handleBulkUpdateCategory}
              rules={rules}
              budgetItems={currentMonth.items}
              categories={categories}
              onOpenAddBudgetItemModal={handleOpenAddBudgetItemModal}
            />
          )}

          {activeTab === "enablebanking" && (
            <EnableBankingView
              bankAccount={primaryBankAccount}
              savingsGoals={savingsGoals}
              currentMonth={currentMonth}
              transactions={transactions}
              onSync={handleBankSync}
              isSyncing={isSyncing}
              onOpenAddGoal={handleOpenAddSavingsGoal}
            />
          )}

          {activeTab === "categorieen" && (
            <CategoriesView
              currentMonth={currentMonth}
              categories={categories}
              onOpenAddBudgetItem={(grp) => handleOpenAddBudgetItemModal(grp)}
              onOpenEditBudgetItem={handleOpenEditBudgetItem}
              onOpenAddCategory={handleOpenAddCategory}
              onOpenEditCategory={handleOpenEditCategory}
              onDeleteCategory={handleDeleteCategory}
              onUpdateBudgetItem={handleUpdateBudgetItem}
              onDeleteBudgetItem={handleDeleteBudgetItem}
            />
          )}

          {activeTab === "koppelregels" && (
            <KoppelregelsView
              rules={rules}
              onAddRule={() => {
                setInitialRuleKeyword("");
                setInitialRuleGroup("Dagelijks Leven");
                setInitialRuleBudgetItemId("");
                setIsAddRuleModalOpen(true);
              }}
              onToggleRule={handleToggleRule}
              onDeleteRule={handleDeleteRule}
              onApplyRulesToAll={handleApplyRulesToAll}
              transactions={transactions}
              budgetItems={currentMonth.items}
            />
          )}

          {activeTab === "jaaroverzicht" && (
            <YearOverviewView
              allMonths={monthlyBudgets}
              onSelectMonth={(mId) => {
                setSelectedMonthId(mId);
                setActiveTab("maandbegroting");
              }}
            />
          )}
        </main>
      </div>

      {/* Mobile Bottom Navigation Bar */}
      <BottomNav
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        onOpenMobileMenu={() => setIsMobileDrawerOpen(true)}
      />

      {/* Modals */}
      <AddTransactionModal
        isOpen={isAddTxModalOpen}
        onClose={() => setIsAddTxModalOpen(false)}
        onAdd={handleAddTransaction}
      />

      <AddBudgetItemModal
        isOpen={isAddBudgetItemModalOpen}
        onClose={() => setIsAddBudgetItemModalOpen(false)}
        onAdd={handleAddBudgetItem}
        categories={categories}
        defaultGroup={addBudgetItemDefaultGroup}
      />

      <EditBudgetItemModal
        isOpen={!!editingBudgetItem}
        onClose={() => setEditingBudgetItem(null)}
        item={editingBudgetItem}
        currentMonthId={selectedMonthId}
        allMonths={monthlyBudgets}
        categories={categories}
        onSave={handleSaveBudgetItemDetails}
        onDelete={handleDeleteBudgetItem}
      />

      <ManageCategoryModal
        isOpen={isCategoryModalOpen}
        onClose={() => {
          setIsCategoryModalOpen(false);
          setEditingCategory(null);
        }}
        category={editingCategory}
        onSave={handleSaveCategory}
        onDelete={handleDeleteCategory}
      />

      <AddRuleModal
        isOpen={isAddRuleModalOpen}
        onClose={() => setIsAddRuleModalOpen(false)}
        onAdd={handleAddRule}
        initialKeyword={initialRuleKeyword}
        initialGroup={initialRuleGroup}
        initialBudgetItemId={initialRuleBudgetItemId}
        budgetItems={currentMonth.items}
        categories={categories}
        transactions={transactions}
      />

      <AddSavingsGoalModal
        isOpen={isAddSavingsGoalModalOpen}
        onClose={() => {
          setIsAddSavingsGoalModalOpen(false);
          setEditingSavingsGoal(null);
        }}
        onSave={handleSaveSavingsGoal}
        editingGoal={editingSavingsGoal}
        transactions={transactions}
        ownIbans={bankAccounts.map((account) => account.iban).filter(Boolean)}
        budgetItems={currentMonth.items}
      />

      <ItemTransactionsModal
        isOpen={!!itemTransactionsModalItem}
        onClose={() => setItemTransactionsModalItem(null)}
        budgetItem={itemTransactionsModalItem}
        currentMonth={currentMonth}
        allMonths={monthlyBudgets}
        transactions={transactions}
        onUnlinkTransaction={(txId) => {
          const tx = transactions.find((t) => t.id === txId);
          if (!tx) {
            return;
          }
          const next = { ...tx, budgetItemId: undefined, matchedRuleId: undefined };
          void runSave(
            async () => {
              await saveTransaction(next);
              setTransactions((prev) => prev.map((item) => (item.id === txId ? next : item)));
            },
            {
              title: "Transactie ontkoppeld",
              detail: `${tx.description} · ${euro(tx.amount)}`,
              variant: "info",
            }
          );
        }}
        onLinkTransaction={(txId, group, itemId) => {
          handleLinkTransaction(txId, group, itemId);
        }}
        onOpenEditBudgetItem={(item) => handleOpenEditBudgetItem(item)}
        onAddTransactionToItem={(itemId, itemGroup) => {
          setIsAddTxModalOpen(true);
        }}
      />

      <ToastStack toasts={toasts} onDismiss={dismiss} />
    </div>
  );
}

export default App;
