import { defaultReportingMonth } from "./month";
import type { BudgetItem, MonthlyBudget, Transaction } from "./types";
import type { KpiBreakdownColumn, KpiBreakdownRow } from "./components/KpiBreakdownModal.vue";
import {
  budgetItemRows,
  formulaRows,
  isFixedBudgetItem,
  isMonthEntryBudgetItem,
  sumAllPaid,
  sumBudgetDelta,
  sumBudgetedAmount,
  sumBudgetedOver,
  sumBudgetedPaid,
  sumBudgetedRemaining,
  sumFixedBudgetedAmount,
  sumFixedBudgetedPaid,
  sumFixedBudgetedRemaining,
  sumMonthEntryBudgetedAmount,
  sumMonthEntryBudgetedPaid,
  type FormulaLine,
} from "./kpiBreakdown";

export type MonthKpiInput = {
  incomeItems: BudgetItem[];
  expenseItems: BudgetItem[];
  savingsItems: BudgetItem[];
  bankBalance: number;
  /** Ruwe bankmutaties van de maand (dashboard). */
  monthTransactions?: Transaction[];
  /** Dashboard: bank-KPI's uit transacties i.p.v. begrotingsposten (incl. pot-overboekingen). */
  bankTotalsFromTransactions?: boolean;
};

/** Sommeer inkomsten/uitgaven/sparen direct uit bankmutaties (volledig cashflow-beeld). */
export function sumRawBankTotals(transactions: Transaction[]): {
  totalIncomeBank: number;
  totalExpenseBank: number;
  totalSavingsBank: number;
} {
  let income = 0;
  let expense = 0;
  let savings = 0;

  for (const tx of transactions) {
    if (tx.isPending) {
      continue;
    }

    if (tx.type === "Inkomsten") {
      income += Math.abs(tx.amount);
    } else if (tx.type === "Uitgave") {
      expense += Math.abs(tx.amount);
    } else if (tx.type === "Sparen") {
      savings += -tx.amount;
    }
  }

  return {
    totalIncomeBank: income,
    totalExpenseBank: expense,
    totalSavingsBank: savings,
  };
}

export type MonthKpiSnapshot = {
  totalIncomeBudget: number;
  totalIncomeReceived: number;
  totalIncomeRemaining: number;
  totalIncomeOver: number;
  totalIncomeBank: number;
  totalIncomeDelta: number;
  totalExpenseBudget: number;
  totalExpenseFixedBudget: number;
  totalExpenseRulesBudget: number;
  totalExpenseFixedPaid: number;
  totalExpenseFixedRemaining: number;
  totalExpenseRulesPaid: number;
  totalExpensePaid: number;
  totalExpenseRemaining: number;
  totalExpenseOver: number;
  totalExpenseOutsideBudget: number;
  totalExpenseBank: number;
  totalExpenseDelta: number;
  totalSavingsBudget: number;
  totalSavingsPaid: number;
  totalSavingsRemaining: number;
  totalSavingsOver: number;
  totalSavingsBank: number;
  totalSavingsDelta: number;
  plannedSurplus: number;
  plannedSurplusWithRules: number;
  expectedEndOfMonth: number;
  actualCashflow: number;
  netActual: number;
  netBudget: number;
  netBudgetDelta: number;
  expenseFixedCount: number;
};

export type KpiModalBreakdown = {
  title: string;
  formula: string;
  subtitle?: string;
  columns: KpiBreakdownColumn[];
  rows: KpiBreakdownRow[];
  totalValue: number;
  totalLabel?: string;
  totalColorClass?: string;
};

export function computeMonthKpi(input: MonthKpiInput): MonthKpiSnapshot {
  const { incomeItems, expenseItems, savingsItems, bankBalance } = input;

  const totalIncomeBudget = sumBudgetedAmount(incomeItems);
  const totalIncomeReceived = sumBudgetedPaid(incomeItems);
  const totalIncomeRemaining = sumBudgetedRemaining(incomeItems);
  const totalIncomeOver = sumBudgetedOver(incomeItems);
  let totalIncomeBank = sumAllPaid(incomeItems);
  const totalIncomeDelta = sumBudgetDelta(incomeItems);

  const totalExpenseBudget = sumBudgetedAmount(expenseItems);
  const totalExpenseFixedBudget = sumFixedBudgetedAmount(expenseItems);
  const totalExpenseRulesBudget = sumMonthEntryBudgetedAmount(expenseItems);
  const totalExpenseFixedPaid = sumFixedBudgetedPaid(expenseItems);
  const totalExpenseFixedRemaining = sumFixedBudgetedRemaining(expenseItems);
  const totalExpenseRulesPaid = sumMonthEntryBudgetedPaid(expenseItems);
  const totalExpensePaid = sumBudgetedPaid(expenseItems);
  const totalExpenseRemaining = sumBudgetedRemaining(expenseItems);
  const totalExpenseOver = sumBudgetedOver(expenseItems);
  const totalExpenseOutsideBudget = totalExpenseRulesBudget + totalExpenseOver;
  let totalExpenseBank = sumAllPaid(expenseItems);
  const totalExpenseDelta = sumBudgetDelta(expenseItems);

  const totalSavingsBudget = sumBudgetedAmount(savingsItems);
  const totalSavingsPaid = sumBudgetedPaid(savingsItems);
  const totalSavingsRemaining = sumBudgetedRemaining(savingsItems);
  const totalSavingsOver = sumBudgetedOver(savingsItems);
  let totalSavingsBank = sumAllPaid(savingsItems);
  const totalSavingsDelta = sumBudgetDelta(savingsItems);

  if (input.bankTotalsFromTransactions && input.monthTransactions) {
    const raw = sumRawBankTotals(input.monthTransactions);
    totalIncomeBank = raw.totalIncomeBank;
    totalExpenseBank = raw.totalExpenseBank;
    totalSavingsBank = raw.totalSavingsBank;
  }

  const plannedSurplus = totalIncomeBudget - totalExpenseFixedBudget - totalSavingsBudget;
  const plannedSurplusWithRules = totalIncomeBudget - totalExpenseBudget - totalSavingsBudget;
  const expectedEndOfMonth =
    bankBalance +
    totalIncomeRemaining -
    totalExpenseFixedRemaining -
    totalSavingsRemaining -
    totalExpenseRulesBudget -
    totalExpenseOver;
  const actualCashflow = totalIncomeBank - totalExpenseBank - totalSavingsBank;
  const netActual = actualCashflow;
  const netBudget = totalIncomeBudget - totalExpenseBudget - totalSavingsBudget;
  const netBudgetDelta = totalIncomeDelta - totalExpenseDelta - totalSavingsDelta;

  return {
    totalIncomeBudget,
    totalIncomeReceived,
    totalIncomeRemaining,
    totalIncomeOver,
    totalIncomeBank,
    totalIncomeDelta,
    totalExpenseBudget,
    totalExpenseFixedBudget,
    totalExpenseRulesBudget,
    totalExpenseFixedPaid,
    totalExpenseFixedRemaining,
    totalExpenseRulesPaid,
    totalExpensePaid,
    totalExpenseRemaining,
    totalExpenseOver,
    totalExpenseOutsideBudget,
    totalExpenseBank,
    totalExpenseDelta,
    totalSavingsBudget,
    totalSavingsPaid,
    totalSavingsRemaining,
    totalSavingsOver,
    totalSavingsBank,
    totalSavingsDelta,
    plannedSurplus,
    plannedSurplusWithRules,
    expectedEndOfMonth,
    actualCashflow,
    netActual,
    netBudget,
    netBudgetDelta,
    expenseFixedCount: expenseItems.filter(isFixedBudgetItem).length,
  };
}

export function isActiveReportingMonth(
  month: Pick<MonthlyBudget, "monthId" | "year">,
  reportingMonth?: Pick<MonthlyBudget, "monthId" | "year">
): boolean {
  const anchor = reportingMonth ?? defaultReportingMonth();
  return month.monthId === anchor.monthId && month.year === anchor.year;
}

/** Live banksaldo alleen in de huidige rapportagemaand; andere maanden = 0. */
export function resolvePeriodStartBalance(
  month: Pick<MonthlyBudget, "monthId" | "year">,
  liveBalance: number,
  reportingMonth?: Pick<MonthlyBudget, "monthId" | "year">
): number {
  return isActiveReportingMonth(month, reportingMonth) ? liveBalance : 0;
}

function euro(n: number): string {
  return n.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

export function buildForecastFormulaLines(
  kpi: MonthKpiSnapshot,
  bankBalance: number,
  finalTone: "result" | "subresult" = "subresult",
  includeStartBalance = true
): FormulaLine[] {
  const lines: FormulaLine[] = [];
  if (includeStartBalance) {
    lines.push({ id: "balance", label: "Huidig saldo", amount: bankBalance, tone: "neutral" });
  }
  lines.push(
    {
      id: "inc-rem",
      label: "+ Nog te ontvangen (begroot)",
      amount: kpi.totalIncomeRemaining,
      tone: kpi.totalIncomeRemaining > 0 ? "plus" : "neutral",
    },
    {
      id: "exp-rem",
      label: "− Nog te betalen (vast)",
      amount: -kpi.totalExpenseFixedRemaining,
      tone: "minus",
    },
    {
      id: "sav-rem",
      label: "− Nog te sparen (begroot)",
      amount: -kpi.totalSavingsRemaining,
      tone: "minus",
    }
  );
  if (kpi.totalExpenseRulesBudget > 0) {
    lines.push({
      id: "exp-rules-out",
      label: "− Buiten budget (regels)",
      amount: -kpi.totalExpenseRulesBudget,
      tone: "minus",
    });
  }
  if (kpi.totalExpenseOver > 0) {
    lines.push({
      id: "exp-over",
      label: "− Overschrijding",
      amount: -kpi.totalExpenseOver,
      tone: "minus",
    });
  }
  lines.push({
    id: "expected",
    label: "= Verwacht saldo eind maand",
    amount: kpi.expectedEndOfMonth,
    tone: finalTone,
  });
  return lines;
}

export function buildBalanceModalBreakdown(
  kpi: MonthKpiSnapshot,
  bankBalance: number,
  includeStartBalance = true
): KpiModalBreakdown {
  const { columns, rows } = formulaRows(
    buildForecastFormulaLines(kpi, bankBalance, "result", includeStartBalance)
  );
  return {
    title: "Huidig Saldo (ING)",
    formula: includeStartBalance
      ? "Huidig saldo − openstaand − buiten budget − overschrijving = verwacht eind"
      : "Nog te ontvangen − nog te betalen − nog te sparen − buiten budget − overschrijving",
    subtitle: "Zelfde prognose als op maandbegroting",
    columns,
    rows,
    totalValue: kpi.expectedEndOfMonth,
    totalLabel: "Verwacht saldo eind maand",
    totalColorClass: kpi.expectedEndOfMonth >= 0 ? "text-indigo-300" : "text-rose-400",
  };
}

export function buildDashboardExpenseModalBreakdown(
  kpi: MonthKpiSnapshot,
  monthName: string
): KpiModalBreakdown {
  const lines: FormulaLine[] = [
    {
      id: "within",
      label: "Binnen begroting (vast)",
      amount: -kpi.totalExpenseFixedPaid,
      tone: "minus",
    },
  ];
  if (kpi.totalExpenseRulesPaid > 0) {
    lines.push({
      id: "rules-paid",
      label: "Buiten budget (regels, betaald)",
      amount: -kpi.totalExpenseRulesPaid,
      tone: "minus",
    });
  }
  if (kpi.totalExpenseOver > 0) {
    lines.push({
      id: "over",
      label: "Overschrijding",
      amount: -kpi.totalExpenseOver,
      tone: "minus",
    });
  }
  lines.push({
    id: "total",
    label: "Totaal betaald (bank)",
    amount: -kpi.totalExpenseBank,
    tone: "result",
  });
  if (kpi.totalExpenseRulesBudget > 0) {
    lines.push({
      id: "rules-open",
      label: "Nog open buiten budget (regels)",
      amount: -Math.max(0, kpi.totalExpenseRulesBudget - kpi.totalExpenseRulesPaid),
      tone: "minus",
    });
  }
  const { columns, rows } = formulaRows(lines);
  return {
    title: `Uitgaven (${monthName})`,
    formula: "Vast + regels + overschrijding = totaal betaald (bank)",
    subtitle: `Vast begroot: € ${euro(kpi.totalExpenseFixedBudget)} · Buiten budget (regels): € ${euro(kpi.totalExpenseRulesBudget)}`,
    columns,
    rows,
    totalValue: kpi.totalExpenseBank,
    totalColorClass: "text-rose-400",
  };
}

export function buildDashboardIncomeModalBreakdown(
  incomeItems: BudgetItem[],
  kpi: MonthKpiSnapshot,
  monthName: string
): KpiModalBreakdown {
  const { columns, rows } = budgetItemRows(incomeItems, "paidAll");
  return {
    title: `Inkomsten (${monthName})`,
    formula: "Som van alle ontvangen bedragen (bank)",
    subtitle: `Begroot: € ${euro(kpi.totalIncomeBudget)} · Verschil: ${kpi.totalIncomeDelta >= 0 ? "+" : ""}€ ${euro(kpi.totalIncomeDelta)}`,
    columns,
    rows,
    totalValue: kpi.totalIncomeBank,
    totalColorClass: "text-emerald-400",
  };
}

export function buildNettoModalBreakdown(
  kpi: MonthKpiSnapshot,
  options: {
    mode: "budget" | "dashboard";
    bankBalance: number;
    includeStartBalance?: boolean;
    monthName?: string;
    onOpenItem?: (item: BudgetItem) => void;
    incomeItems?: BudgetItem[];
    expenseItems?: BudgetItem[];
    savingsItems?: BudgetItem[];
  }
): KpiModalBreakdown {
  if (options.mode === "dashboard") {
    const lines: FormulaLine[] = [
      { id: "sec-cashflow", label: "1 · Werkelijk cashflow (bank)", tone: "section" },
      { id: "inc-bank", label: "Ontvangen", amount: kpi.totalIncomeBank, tone: "plus" },
      { id: "exp-bank", label: "− Uitgegeven", amount: -kpi.totalExpenseBank, tone: "minus" },
      { id: "sav-bank", label: "− Gespaard", amount: -kpi.totalSavingsBank, tone: "minus" },
      { id: "actual", label: "= Werkelijk netto", amount: kpi.netActual, tone: "subresult" },
      { id: "sec-delta", label: "2 · Verschil t.o.v. begroting", tone: "section" },
      { id: "inc-delta", label: "Inkomsten vs begroting", amount: kpi.totalIncomeDelta, tone: "plus" },
      { id: "exp-delta", label: "Uitgaven vs begroting", amount: kpi.totalExpenseDelta, tone: "minus" },
      {
        id: "net-delta",
        label: "= Netto vs begroting",
        amount: kpi.netBudgetDelta,
        tone: "subresult",
      },
      { id: "sec-forecast", label: "3 · Prognose saldo eind maand", tone: "section" },
      ...buildForecastFormulaLines(
        kpi,
        options.bankBalance,
        "subresult",
        options.includeStartBalance ?? true
      ),
    ];
    const { columns, rows } = formulaRows(lines);
    return {
      title: "Netto Overschot / Saldo",
      formula: "Werkelijk netto · verschil vs begroting · prognose eind saldo",
      subtitle: "Werkelijk = alle bankbedragen; prognose = zelfde formule als maandbegroting",
      columns,
      rows,
      totalValue: kpi.netActual,
      totalLabel: "Werkelijk netto",
      totalColorClass: kpi.netActual >= 0 ? "text-emerald-400" : "text-rose-400",
    };
  }

  const nettoLines: FormulaLine[] = [
    {
      id: "planned-card",
      label: "Netto overschot (vast) — hoofdgetal kaart",
      amount: kpi.plannedSurplus,
      tone: "neutral",
    },
    { id: "sec-forecast", label: "Prognose saldo eind maand", tone: "section" },
    ...buildForecastFormulaLines(
      kpi,
      options.bankBalance,
      "result",
      options.includeStartBalance ?? true
    ),
  ];
  const { columns, rows } = formulaRows(nettoLines);
  return {
    title: "Netto Overschot / Saldo",
    formula: (options.includeStartBalance ?? true)
      ? "Huidig saldo − openstaand − buiten budget (regels) − overschrijding"
      : "Nog te ontvangen − nog te betalen − nog te sparen − buiten budget − overschrijding",
    subtitle: `Hoofdgetal kaart = begroot netto (vast) € ${euro(kpi.plannedSurplus)}`,
    columns,
    rows,
    totalLabel: "Verwacht saldo eind maand",
    totalValue: kpi.expectedEndOfMonth,
    totalColorClass: kpi.expectedEndOfMonth >= 0 ? "text-indigo-300" : "text-rose-400",
  };
}

export function buildBudgetIncomeModalBreakdown(
  incomeItems: BudgetItem[],
  kpi: MonthKpiSnapshot,
  onOpenItem?: (item: BudgetItem) => void
): KpiModalBreakdown {
  const { columns, rows } = budgetItemRows(incomeItems, "budget", onOpenItem);
  return {
    title: "Totaal Inkomsten",
    formula: "Som van begrote inkomstenposten (budget)",
    subtitle: onOpenItem
      ? "Klik op een post om banktransacties te openen"
      : "Begroot per inkomstenpost, met bankbedrag ernaast",
    columns,
    rows,
    totalValue: kpi.totalIncomeBudget,
    totalColorClass: "text-emerald-400",
  };
}

export function buildBudgetExpenseModalBreakdown(
  expenseItems: BudgetItem[],
  kpi: MonthKpiSnapshot,
  onOpenItem?: (item: BudgetItem) => void
): KpiModalBreakdown {
  const fixedItems = expenseItems.filter(isFixedBudgetItem);
  const ruleItems = expenseItems.filter(isMonthEntryBudgetItem);
  const { columns, rows } = budgetItemRows(fixedItems, "budget", onOpenItem);
  return {
    title: "Totaal Uitgaven",
    formula: "Vaste begrote uitgaven (zonder regels per maand)",
    subtitle:
      ruleItems.length > 0
        ? `${fixedItems.length} vast · buiten budget (regels): € ${euro(kpi.totalExpenseRulesBudget)}`
        : `${fixedItems.length} vaste begrotingsposten`,
    columns,
    rows,
    totalValue: kpi.totalExpenseFixedBudget,
    totalColorClass: "text-rose-400",
  };
}

export function buildBudgetSavingsModalBreakdown(
  savingsItems: BudgetItem[],
  kpi: MonthKpiSnapshot,
  onOpenItem?: (item: BudgetItem) => void
): KpiModalBreakdown {
  const { columns, rows } = budgetItemRows(savingsItems, "budget", onOpenItem);
  return {
    title: "Totaal Spaargeld",
    formula: "Som van begrote spaardoelen (budget)",
    subtitle:
      kpi.totalSavingsOver > 0
        ? `Buiten budget gespaard: € ${euro(kpi.totalSavingsOver)}`
        : onOpenItem
          ? "Klik op een post om banktransacties te openen"
          : "Begroot per spaardoel, met bankbedrag ernaast",
    columns,
    rows,
    totalValue: kpi.totalSavingsBudget,
    totalColorClass: "text-blue-400",
  };
}
