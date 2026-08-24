import type { CategoryDefinition, Rule, SavingsGoal, Transaction } from "./types";

function csrfHeaders(): HeadersInit {
  const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

  return {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
    "X-XSRF-TOKEN": match ? decodeURIComponent(match[1]) : "",
  };
}

async function sparenRequest(method: string, path: string, body?: unknown) {
  const response = await fetch(path, {
    method,
    credentials: "same-origin",
    headers: csrfHeaders(),
    body: body === undefined ? undefined : JSON.stringify(body),
  });

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    throw new Error(data.error || "Kon wijziging niet opslaan");
  }

  return data;
}

export async function loadSparenState() {
  return sparenRequest("GET", "/api/sparen/state");
}

export async function saveBudgetItem(itemId: string, payload: Record<string, unknown>) {
  return sparenRequest("PUT", `/api/sparen/budget-items/${encodeURIComponent(itemId)}`, payload);
}

export async function deleteBudgetItem(itemId: string) {
  return sparenRequest("DELETE", `/api/sparen/budget-items/${encodeURIComponent(itemId)}`);
}

export function transactionWritePayload(tx: Transaction) {
  return {
    id: tx.id,
    date: tx.date,
    time: tx.time ?? null,
    description: tx.description,
    amount: tx.amount,
    type: tx.type,
    categoryGroup: tx.categoryGroup,
    budgetItemId: tx.budgetItemId ?? null,
    accountIban: tx.accountIban,
    counterparty: tx.counterparty ?? null,
    isPending: Boolean(tx.isPending),
    matchedRuleId: tx.matchedRuleId ?? null,
    linkExcluded: Boolean(tx.linkExcluded),
    linkExclusionReason: tx.linkExclusionReason ?? null,
    source: tx.source,
  };
}

export function ruleWritePayload(rule: Rule) {
  return {
    name: rule.name,
    keyword: rule.keyword,
    matchField: rule.matchField,
    targetGroup: rule.targetGroup,
    targetBudgetItemId: rule.targetBudgetItemId ?? null,
    targetType: rule.targetType,
    isActive: rule.isActive,
  };
}

export function categoryWritePayload(category: CategoryDefinition) {
  return {
    name: category.name,
    type: category.type,
    icon: category.icon ?? null,
    color: category.color ?? null,
    description: category.description ?? null,
    isDefault: Boolean(category.isDefault),
  };
}

export function savingsGoalWritePayload(goal: SavingsGoal) {
  return {
    name: goal.name,
    accountIban: goal.accountIban,
    bankName: goal.bankName,
    targetAmount: goal.targetAmount,
    initialAmount: goal.initialAmount,
    monthlyContribution: goal.monthlyContribution,
    color: goal.color,
    iconName: goal.iconName,
    notes: goal.notes ?? null,
    categoryBudgetItemId: goal.categoryBudgetItemId ?? null,
    kind: goal.kind === "pot" ? "pot" : "goal",
  };
}

export async function saveTransaction(tx: Transaction) {
  return sparenRequest(
    "PUT",
    `/api/sparen/transactions/${encodeURIComponent(tx.id)}`,
    transactionWritePayload(tx)
  );
}

export async function saveTransactions(txs: Transaction[]) {
  if (txs.length === 0) {
    return { ok: true };
  }

  return sparenRequest("PUT", "/api/sparen/transactions", {
    items: txs.map(transactionWritePayload),
  });
}

export async function deleteTransactionRecord(txId: string) {
  return sparenRequest("DELETE", `/api/sparen/transactions/${encodeURIComponent(txId)}`);
}

export async function saveRule(rule: Rule) {
  return sparenRequest("PUT", `/api/sparen/rules/${encodeURIComponent(rule.id)}`, ruleWritePayload(rule));
}

export async function deleteRuleRecord(ruleId: string) {
  return sparenRequest("DELETE", `/api/sparen/rules/${encodeURIComponent(ruleId)}`);
}

export async function saveCategoryRecord(category: CategoryDefinition) {
  return sparenRequest(
    "PUT",
    `/api/sparen/categories/${encodeURIComponent(category.id)}`,
    categoryWritePayload(category)
  );
}

export async function deleteCategoryRecord(categoryId: string) {
  return sparenRequest("DELETE", `/api/sparen/categories/${encodeURIComponent(categoryId)}`);
}

export async function saveSavingsGoalRecord(goal: SavingsGoal) {
  return sparenRequest(
    "PUT",
    `/api/sparen/savings-goals/${encodeURIComponent(goal.id)}`,
    savingsGoalWritePayload(goal)
  );
}

export async function deleteSavingsGoalRecord(goalId: string) {
  return sparenRequest("DELETE", `/api/sparen/savings-goals/${encodeURIComponent(goalId)}`);
}

export async function syncSparenBank() {
  const response = await fetch("/api/sparen/sync-bank", {
    method: "POST",
    credentials: "same-origin",
    headers: csrfHeaders(),
  });

  const data = await response.json().catch(() => ({}));

  if (response.status === 409 && data.url) {
    window.location.href = data.url;
    return data;
  }

  if (!response.ok) {
    throw new Error(data.error || "Banksynchronisatie mislukt");
  }

  return data;
}
