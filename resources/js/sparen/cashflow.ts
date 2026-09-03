import type { MonthlyBudget, SavingsGoal, Transaction } from "./types";
import { MONTH_ID_TO_NUMBER, isTransactionInReportingMonth, reportingPeriodForMonth } from "./month";
import { isUnlinkedTransaction } from "./matchRule";
import {
  isSavingsCashflowTransfer,
  isSavingsDepositTransaction,
  isSavingsWithdrawalTransaction,
  parseIngSavingsDestination,
  transactionMatchesSavingsGoal,
} from "./matchSavings";
import { computePotSettlement, goalBudgetItemIds, isPotGoal } from "./potSettlement";
import { sumBudgetedPaid } from "./kpiBreakdown";

export type CashflowBucket =
  | "received"
  | "spent"
  | "toSavings"
  | "fromSavings"
  | "toSavingsGoals"
  | "toSavingsPots"
  | "fromSavingsGoals"
  | "fromSavingsPots"
  | "unlinked"
  | "netSavings";

export type CashflowTransactions = {
  received: Transaction[];
  spent: Transaction[];
  toSavingsGoals: Transaction[];
  toSavingsPots: Transaction[];
  fromSavingsGoals: Transaction[];
  fromSavingsPots: Transaction[];
  unlinked: Transaction[];
};

export type PeriodCashflow = {
  received: number;
  spent: number;
  toSavings: number;
  toSavingsGoals: number;
  toSavingsPots: number;
  fromSavings: number;
  fromSavingsGoals: number;
  fromSavingsPots: number;
  netSavings: number;
  netFromAccount: number;
  unlinkedSpent: number;
  transactions: CashflowTransactions;
};

export type BudgetBankRow = {
  id: string;
  label: string;
  budgetAmount: number;
  budgetHint: string;
  bankAmount: number;
  bankHint: string;
  bucket?: CashflowBucket;
};

export { isSavingsCashflowTransfer };

export type BalancePoint = {
  date: string;
  balance: number;
};

export type MonthFlowRow = {
  monthId: string;
  monthName: string;
  short: string;
  deposited: number;
  withdrawn: number;
  net: number;
  isCurrent: boolean;
  isFuture: boolean;
};

export type MonthBalanceRow = {
  monthId: string;
  short: string;
  balance: number;
  isCurrent: boolean;
  isFuture: boolean;
  isUnknown?: boolean;
};

export function compactEuro(n: number): string {
  const abs = Math.abs(n);
  const sign = n < 0 ? "−" : "";
  if (abs >= 10000) {
    return `${sign}€${Math.round(abs / 1000)}k`;
  }
  return `${sign}€${Math.round(abs).toLocaleString("nl-NL")}`;
}

function emptyCashflowTransactions(): CashflowTransactions {
  return {
    received: [],
    spent: [],
    toSavingsGoals: [],
    toSavingsPots: [],
    fromSavingsGoals: [],
    fromSavingsPots: [],
    unlinked: [],
  };
}

function compareTxNewestFirst(a: Transaction, b: Transaction): number {
  const dateCmp = b.date.localeCompare(a.date);
  if (dateCmp !== 0) {
    return dateCmp;
  }
  return (b.time ?? "").localeCompare(a.time ?? "");
}

export function cashflowBucketTransactions(
  flow: PeriodCashflow,
  bucket: CashflowBucket
): Transaction[] {
  const txs = flow.transactions;
  switch (bucket) {
    case "received":
      return txs.received;
    case "spent":
      return txs.spent;
    case "toSavingsGoals":
      return txs.toSavingsGoals;
    case "toSavingsPots":
      return txs.toSavingsPots;
    case "fromSavingsGoals":
      return txs.fromSavingsGoals;
    case "fromSavingsPots":
      return txs.fromSavingsPots;
    case "toSavings":
      return [...txs.toSavingsGoals, ...txs.toSavingsPots].sort(compareTxNewestFirst);
    case "fromSavings":
      return [...txs.fromSavingsGoals, ...txs.fromSavingsPots].sort(compareTxNewestFirst);
    case "netSavings":
      return [
        ...txs.toSavingsGoals,
        ...txs.toSavingsPots,
        ...txs.fromSavingsGoals,
        ...txs.fromSavingsPots,
      ].sort(compareTxNewestFirst);
    case "unlinked":
      return txs.unlinked;
  }
}

export function savingsCashflowTarget(
  tx: Pick<Transaction, "description" | "counterparty" | "assignedSavingsGoalId">,
  goals: SavingsGoal[]
): "pot" | "goal" {
  for (const goal of goals) {
    if (!transactionMatchesSavingsGoal(tx, goal)) {
      continue;
    }
    return isPotGoal(goal) ? "pot" : "goal";
  }

  return parseIngSavingsDestination(tx.description)?.isSpaarpot ? "pot" : "goal";
}

export function reportingMonthIndex(month: Pick<MonthlyBudget, "monthId" | "year">): number {
  const monthNumber = Number.parseInt(MONTH_ID_TO_NUMBER[month.monthId] ?? "1", 10);
  return month.year * 12 + monthNumber;
}

export function computePeriodCashflow(
  transactions: Transaction[],
  month: Pick<MonthlyBudget, "monthId" | "year">,
  goals: SavingsGoal[] = []
): PeriodCashflow {
  const collected = emptyCashflowTransactions();
  let received = 0;
  let spent = 0;
  let toSavingsGoals = 0;
  let toSavingsPots = 0;
  let fromSavingsGoals = 0;
  let fromSavingsPots = 0;
  let unlinkedSpent = 0;

  for (const tx of transactions) {
    if (tx.isPending || !isTransactionInReportingMonth(tx, month)) {
      continue;
    }

    if (isSavingsCashflowTransfer(tx)) {
      const target = savingsCashflowTarget(tx, goals);
      const amount = Math.abs(tx.amount);
      if (isSavingsDepositTransaction(tx)) {
        if (target === "pot") {
          toSavingsPots += amount;
          collected.toSavingsPots.push(tx);
        } else {
          toSavingsGoals += amount;
          collected.toSavingsGoals.push(tx);
        }
      } else if (isSavingsWithdrawalTransaction(tx)) {
        if (target === "pot") {
          fromSavingsPots += amount;
          collected.fromSavingsPots.push(tx);
        } else {
          fromSavingsGoals += amount;
          collected.fromSavingsGoals.push(tx);
        }
      }
      continue;
    }

    if (tx.amount > 0) {
      received += tx.amount;
      collected.received.push(tx);
    } else if (tx.amount < 0) {
      const abs = Math.abs(tx.amount);
      spent += abs;
      collected.spent.push(tx);
      if (isUnlinkedTransaction(tx)) {
        unlinkedSpent += abs;
        collected.unlinked.push(tx);
      }
    }
  }

  const toSavings = toSavingsGoals + toSavingsPots;
  const fromSavings = fromSavingsGoals + fromSavingsPots;
  const netSavings = toSavings - fromSavings;

  for (const list of Object.values(collected)) {
    list.sort(compareTxNewestFirst);
  }

  return {
    received,
    spent,
    toSavings,
    toSavingsGoals,
    toSavingsPots,
    fromSavings,
    fromSavingsGoals,
    fromSavingsPots,
    netSavings,
    netFromAccount: received - spent - netSavings,
    unlinkedSpent,
    transactions: collected,
  };
}

export function budgetVsBankRows(
  month: MonthlyBudget,
  transactions: Transaction[],
  goals: SavingsGoal[]
): BudgetBankRow[] {
  const flow = computePeriodCashflow(transactions, month, goals);
  const rows: BudgetBankRow[] = [];
  let potPaid = 0;
  let potPin = 0;

  for (const goal of goals.filter(isPotGoal)) {
    const settlement = computePotSettlement(goal, month, transactions);
    const linkedPaid = sumBudgetedPaid(settlement.budgetItems);
    rows.push({
      id: goal.id,
      label: settlement.budgetItem?.name ?? goal.name,
      budgetAmount: linkedPaid > 0 ? linkedPaid : settlement.budgeted,
      budgetHint: "envelop",
      bankAmount: settlement.spent,
      bankHint: "pin",
    });
    potPaid += linkedPaid > 0 ? linkedPaid : settlement.budgeted;
    potPin += settlement.spent;
  }

  const expensePaid = sumBudgetedPaid(month.items.filter((item) => item.type === "uitgaven"));
  rows.push({
    id: "overige",
    label: "Overige uitgavenposten",
    budgetAmount: Math.max(0, expensePaid - potPaid),
    budgetHint: "betaald",
    bankAmount: Math.max(0, flow.spent - potPin),
    bankHint: "",
  });
  rows.push({
    id: "unlinked",
    label: "Ongekoppeld",
    budgetAmount: 0,
    budgetHint: "",
    bankAmount: flow.unlinkedSpent,
    bankHint: "",
    bucket: "unlinked",
  });

  return rows;
}

export function groceriesPinSpent(
  month: MonthlyBudget,
  transactions: Transaction[],
  goals: SavingsGoal[]
): number {
  const groceryItemIds = new Set(
    month.items.filter((item) => /boodschap/i.test(item.name)).map((item) => item.id)
  );

  const goal =
    goals.find(
      (candidate) =>
        isPotGoal(candidate) &&
        (/boodschap/i.test(candidate.name) ||
          goalBudgetItemIds(candidate).some((id) => groceryItemIds.has(id)))
    ) ?? null;

  if (!goal) {
    return 0;
  }

  return computePotSettlement(goal, month, transactions).spent;
}

export function eachIsoDate(start: string, end: string): string[] {
  const dates: string[] = [];
  let current = start.slice(0, 10);
  const last = end.slice(0, 10);
  while (current <= last) {
    dates.push(current);
    current = addUtcDays(current, 1);
  }
  return dates;
}

export function checkingBalanceSeries(input: {
  transactions: Transaction[];
  month: Pick<MonthlyBudget, "monthId" | "year">;
  liveBalance: number;
  isCurrentMonth: boolean;
  fallbackStartBalance: number;
  today?: string;
}): BalancePoint[] {
  const period = reportingPeriodForMonth(input.month);
  const today = (input.today ?? localIsoDate()).slice(0, 10);
  const end = input.isCurrentMonth ? (today < period.end ? today : period.end) : period.end;
  const txs = input.transactions.filter(
    (tx) => !tx.isPending && tx.date.slice(0, 10) >= period.start && tx.date.slice(0, 10) <= end
  );
  const start = input.isCurrentMonth
    ? input.liveBalance - txs.reduce((sum, tx) => sum + tx.amount, 0)
    : input.fallbackStartBalance;

  const byDate = new Map<string, number>();
  for (const tx of txs) {
    const day = tx.date.slice(0, 10);
    byDate.set(day, (byDate.get(day) ?? 0) + tx.amount);
  }

  let balance = start;
  return eachIsoDate(period.start, end).map((date) => {
    balance += byDate.get(date) ?? 0;
    return { date, balance };
  });
}

export function savingsFlowByMonths(
  transactions: Transaction[],
  months: MonthlyBudget[],
  goals: SavingsGoal[],
  current: Pick<MonthlyBudget, "monthId" | "year">
): MonthFlowRow[] {
  const currentIndex = reportingMonthIndex(current);
  return months.map((month) => {
    const flow = computePeriodCashflow(transactions, month, goals);
    const index = reportingMonthIndex(month);
    return {
      monthId: month.monthId,
      monthName: month.monthName,
      short: month.monthName.slice(0, 3).toLowerCase(),
      deposited: flow.toSavings,
      withdrawn: flow.fromSavings,
      net: flow.netSavings,
      isCurrent: index === currentIndex,
      isFuture: index > currentIndex,
    };
  });
}

export function monthEndBalances(
  months: MonthlyBudget[],
  current: Pick<MonthlyBudget, "monthId" | "year">,
  liveBalance: number,
  transactions: Transaction[] = []
): MonthBalanceRow[] {
  const currentIndex = reportingMonthIndex(current);
  const txs = transactions.filter((tx) => !tx.isPending);
  const firstTxDate = txs.reduce((min, tx) => {
    const day = tx.date.slice(0, 10);
    return day < min ? day : min;
  }, "9999-12-31");

  return months.map((month) => {
    const index = reportingMonthIndex(month);
    const isCurrent = index === currentIndex;
    const isFuture = index > currentIndex;
    if (isCurrent) {
      return {
        monthId: month.monthId,
        short: month.monthName.slice(0, 3).toLowerCase(),
        balance: liveBalance,
        isCurrent,
        isFuture,
      };
    }
    if (isFuture) {
      return {
        monthId: month.monthId,
        short: month.monthName.slice(0, 3).toLowerCase(),
        balance: 0,
        isCurrent,
        isFuture,
      };
    }

    if (month.endBalanceCaptured && month.endBalance != null) {
      return {
        monthId: month.monthId,
        short: month.monthName.slice(0, 3).toLowerCase(),
        balance: month.endBalance,
        isCurrent,
        isFuture,
      };
    }

    const period = reportingPeriodForMonth(month);
    if (firstTxDate !== "9999-12-31" && period.end < firstTxDate) {
      return {
        monthId: month.monthId,
        short: month.monthName.slice(0, 3).toLowerCase(),
        balance: 0,
        isCurrent,
        isFuture,
        isUnknown: true,
      };
    }

    const afterEnd = txs.filter((tx) => tx.date.slice(0, 10) > period.end);
    const balance = liveBalance - afterEnd.reduce((sum, tx) => sum + tx.amount, 0);
    return {
      monthId: month.monthId,
      short: month.monthName.slice(0, 3).toLowerCase(),
      balance,
      isCurrent,
      isFuture,
    };
  });
}

function addUtcDays(iso: string, days: number): string {
  const [year, month, day] = iso.slice(0, 10).split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day + days));
  return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, "0")}-${String(date.getUTCDate()).padStart(2, "0")}`;
}

function localIsoDate(now = new Date()): string {
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, "0")}-${String(now.getDate()).padStart(2, "0")}`;
}
