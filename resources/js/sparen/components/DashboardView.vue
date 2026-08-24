<script setup lang="ts">
import { computed, ref, type Component } from "vue";
import {
  TrendingUp,
  TrendingDown,
  Wallet,
  PiggyBank,
  CheckCircle,
  Clock,
  ArrowUpRight,
  ShieldCheck,
  Building2,
  Car,
  Receipt,
  ShoppingBag,
  ExternalLink,
  Filter,
  Layers,
} from "lucide-vue-next";
import type { MonthlyBudget, Transaction, BankAccount, ActiveTab, BudgetItem } from "../types";
import TransactionDate from "./TransactionDate.vue";
import KpiBreakdownModal from "./KpiBreakdownModal.vue";
import { defaultReportingMonth } from "../month";
import {
  buildBalanceModalBreakdown,
  buildDashboardExpenseModalBreakdown,
  buildDashboardIncomeModalBreakdown,
  buildNettoModalBreakdown,
  computeMonthKpi,
  isActiveReportingMonth,
  resolvePeriodStartBalance,
  sumRawBankTotals,
} from "../monthKpi";
import { isTransactionInReportingMonth } from "../month";

type DashboardKpiKey = "balance" | "income" | "expense" | "netto";

const props = defineProps<{
  currentMonth: MonthlyBudget;
  allMonths: MonthlyBudget[];
  transactions: Transaction[];
  bankAccount: BankAccount;
  onNavigateTab: (tab: ActiveTab) => void;
}>();

const chartView = ref<"incomeExpense" | "netCashflow">("incomeExpense");
const chartMode = ref<"actual" | "budget">("actual");
const kpiKey = ref<DashboardKpiKey | null>(null);

function budgetAmount(item: BudgetItem): number {
  return Number(item.actual ?? item.estimated ?? 0);
}

function paidAmount(item: BudgetItem): number {
  return Number(item.paidOrReceived ?? 0);
}

function monthLabel(month: MonthlyBudget): string {
  return (month.monthName || "").slice(0, 3);
}

const incomeItems = computed(() => props.currentMonth.items.filter((i) => i.type === "inkomsten"));
const expenseItems = computed(() => props.currentMonth.items.filter((i) => i.type === "uitgaven"));
const savingsItems = computed(() => props.currentMonth.items.filter((i) => i.type === "sparen"));

const reportingAnchor = computed(() => defaultReportingMonth());

const liveAccountBalance = computed(() => Number(props.bankAccount.balance ?? 0));

const isCurrentReportingMonth = computed(() =>
  isActiveReportingMonth(props.currentMonth, reportingAnchor.value)
);

const periodStartBalance = computed(() =>
  resolvePeriodStartBalance(
    props.currentMonth,
    liveAccountBalance.value,
    reportingAnchor.value
  )
);

const monthTransactions = computed(() =>
  props.transactions.filter(
    (tx) => isTransactionInReportingMonth(tx, props.currentMonth) && !tx.isPending
  )
);

const monthKpi = computed(() =>
  computeMonthKpi({
    incomeItems: incomeItems.value,
    expenseItems: expenseItems.value,
    savingsItems: savingsItems.value,
    bankBalance: periodStartBalance.value,
    monthTransactions: monthTransactions.value,
    bankTotalsFromTransactions: true,
  })
);

const totalIncomeBudget = computed(() => monthKpi.value.totalIncomeBudget);
const totalIncomeReceived = computed(() => monthKpi.value.totalIncomeBank);
const totalIncomeDelta = computed(() => monthKpi.value.totalIncomeDelta);
const totalIncomeOver = computed(() => monthKpi.value.totalIncomeOver);
const totalExpenseFixedBudget = computed(() => monthKpi.value.totalExpenseFixedBudget);
const totalExpenseRulesBudget = computed(() => monthKpi.value.totalExpenseRulesBudget);
const totalExpensePaid = computed(() => monthKpi.value.totalExpenseBank);
const totalExpenseFixedPaid = computed(() => monthKpi.value.totalExpenseFixedPaid);
const totalExpenseOver = computed(() => monthKpi.value.totalExpenseOver);
const totalExpenseDelta = computed(() => monthKpi.value.totalExpenseDelta);
const totalExpenseFixedRemaining = computed(() => monthKpi.value.totalExpenseFixedRemaining);
const totalExpenseRemaining = computed(() => monthKpi.value.totalExpenseRemaining);
const expectedEndOfMonth = computed(() => monthKpi.value.expectedEndOfMonth);
const netActual = computed(() => monthKpi.value.netActual);
const netBudgetDelta = computed(() => monthKpi.value.netBudgetDelta);
const freeToSpend = computed(() => monthKpi.value.expectedEndOfMonth);

const unpaidExpenses = computed(() =>
  expenseItems.value.filter((i) => budgetAmount(i) > paidAmount(i) && budgetAmount(i) > 0)
);

function itemMatchesAliases(item: BudgetItem, aliases: string[]) {
  const group = (item.group || "").toLowerCase().trim();
  return aliases.some((alias) => alias.toLowerCase().trim() === group);
}

type CatDef = {
  name: string;
  type: "inkomsten" | "uitgaven" | "sparen";
  aliases: string[];
  icon: Component;
  items: BudgetItem[];
};

const categoryGroups = computed((): CatDef[] => {
  const mainCategories = [
    { name: "Inkomsten", type: "inkomsten" as const, aliases: ["Inkomsten"], icon: TrendingUp },
    { name: "Woning", type: "uitgaven" as const, aliases: ["Woning"], icon: Building2 },
    {
      name: "Dagelijks Leven",
      type: "uitgaven" as const,
      aliases: ["Dagelijks Leven"],
      icon: ShoppingBag,
    },
    { name: "Leningen & Hypotheek", type: "uitgaven" as const, aliases: ["Leningen"], icon: Receipt },
    {
      name: "Vervoersmiddelen",
      type: "uitgaven" as const,
      aliases: ["Vervoersmiddelen"],
      icon: Car,
    },
    { name: "Verzekeringen", type: "uitgaven" as const, aliases: ["Verzekeringen"], icon: ShieldCheck },
    {
      name: "Sparen & Buffer",
      type: "sparen" as const,
      aliases: ["Spaargeld", "Sparen"],
      icon: PiggyBank,
    },
    {
      name: "Overige Vaste Kosten",
      type: "uitgaven" as const,
      aliases: ["Overige Vaste Kosten"],
      icon: Layers,
    },
    { name: "Overige Kosten", type: "uitgaven" as const, aliases: ["Overige Kosten"], icon: Filter },
  ];

  const groups: CatDef[] = mainCategories.map((cat) => ({
    ...cat,
    items:
      cat.type === "sparen"
        ? savingsItems.value
        : cat.type === "inkomsten"
          ? incomeItems.value
          : props.currentMonth.items.filter(
              (i) => i.type === "uitgaven" && itemMatchesAliases(i, cat.aliases)
            ),
  }));

  const assignedIds = new Set(groups.flatMap((cat) => cat.items.map((i) => i.id)));
  const leftoverNames = [
    ...new Set(
      props.currentMonth.items
        .filter((i) => !assignedIds.has(i.id))
        .map((i) => i.group)
        .filter(Boolean)
    ),
  ];
  leftoverNames.forEach((groupName) => {
    const items = props.currentMonth.items.filter((i) => i.group === groupName);
    groups.push({
      name: groupName,
      type: items[0]?.type ?? "uitgaven",
      aliases: [groupName],
      icon: Layers,
      items,
    });
  });

  return groups;
});

const annualChartData = computed(() =>
  props.allMonths.map((m) => {
    if (chartMode.value === "budget") {
      const inc = m.items
        .filter((i) => i.type === "inkomsten")
        .reduce((acc, x) => acc + budgetAmount(x), 0);
      const exp = m.items
        .filter((i) => i.type === "uitgaven")
        .reduce((acc, x) => acc + budgetAmount(x), 0);
      const sav = m.items
        .filter((i) => i.type === "sparen")
        .reduce((acc, x) => acc + budgetAmount(x), 0);
      return {
        month: monthLabel(m),
        fullName: m.monthName,
        Inkomsten: Math.round(inc),
        Uitgaven: Math.round(exp),
        Sparen: Math.round(sav),
        Netto: Math.round(inc - exp - sav),
      };
    }

    const monthTxs = props.transactions.filter(
      (tx) => isTransactionInReportingMonth(tx, m) && !tx.isPending
    );
    const raw = sumRawBankTotals(monthTxs);

    return {
      month: monthLabel(m),
      fullName: m.monthName,
      Inkomsten: Math.round(raw.totalIncomeBank),
      Uitgaven: Math.round(raw.totalExpenseBank),
      Sparen: Math.round(raw.totalSavingsBank),
      Netto: Math.round(
        raw.totalIncomeBank - raw.totalExpenseBank - raw.totalSavingsBank
      ),
    };
  })
);

const chartMax = computed(() => {
  const vals = annualChartData.value.flatMap((d) =>
    chartView.value === "incomeExpense"
      ? [d.Inkomsten, d.Uitgaven, d.Sparen]
      : [Math.abs(d.Netto)]
  );
  return Math.max(1, ...vals);
});

const kpiBreakdown = computed(() => {
  const key = kpiKey.value;
  if (!key) return null;
  const kpi = monthKpi.value;

  if (key === "balance") {
    return buildBalanceModalBreakdown(
      kpi,
      periodStartBalance.value,
      isCurrentReportingMonth.value
    );
  }
  if (key === "income") {
    return buildDashboardIncomeModalBreakdown(
      incomeItems.value,
      kpi,
      props.currentMonth.monthName
    );
  }
  if (key === "expense") {
    return buildDashboardExpenseModalBreakdown(kpi, props.currentMonth.monthName);
  }
  return buildNettoModalBreakdown(kpi, {
    mode: "dashboard",
    bankBalance: periodStartBalance.value,
    includeStartBalance: isCurrentReportingMonth.value,
  });
});

function barPct(value: number) {
  return `${Math.max(2, Math.round((Math.abs(value) / chartMax.value) * 100))}%`;
}

function catStats(cat: CatDef) {
  const groupEstimated = cat.items.reduce((s, i) => s + budgetAmount(i), 0);
  const groupPaid = cat.items.reduce((s, i) => s + paidAmount(i), 0);
  const percentage =
    groupEstimated > 0 ? Math.min(100, Math.round((groupPaid / groupEstimated) * 100)) : 0;
  const isOverBudget = groupPaid > groupEstimated && groupEstimated > 0;
  return { groupEstimated, groupPaid, percentage, isOverBudget };
}
</script>

<template>
  <div id="dashboard-view" class="space-y-6">
    <div
      class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-slate-900/80 border border-slate-800 p-5 rounded-2xl shadow-sm"
    >
      <div>
        <h2 class="text-xl font-bold text-white tracking-tight flex items-center gap-2">
          Financieel Overzicht • {{ currentMonth.monthName }} {{ currentMonth.year }}
        </h2>
        <p class="text-sm text-slate-400 mt-0.5">
          Realtime status gesynchroniseerd met ING Bank (IBAN:
          <span class="font-mono text-slate-300">NL83 INGB 0004 5658 68</span>)
        </p>
      </div>
      <div class="flex items-center gap-3">
        <div class="bg-slate-800/80 border border-slate-700 px-3.5 py-2 rounded-xl text-right">
          <span class="text-[11px] text-slate-400 block font-medium">Verwacht eind saldo:</span>
          <span
            class="text-base font-bold font-mono"
            :class="freeToSpend >= 0 ? 'text-emerald-400' : 'text-rose-400'"
          >
            € {{ freeToSpend.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
          </span>
        </div>
        <button
          id="dashboard-view-full-budget-btn"
          type="button"
          class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-md shadow-indigo-600/20 transition-all"
          @click="onNavigateTab('maandbegroting')"
        >
          <span>PDF Begroting</span>
          <ExternalLink class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-stretch">
      <button
        type="button"
        class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all relative overflow-hidden h-full flex flex-col"
        @click="kpiKey = 'balance'"
      >
        <div class="flex items-center justify-between text-slate-400 mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider">Huidig Saldo (ING)</span>
          <div
            class="w-8 h-8 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400"
          >
            <Wallet class="w-4 h-4" />
          </div>
        </div>
        <div class="text-2xl font-black text-white font-mono tracking-tight">
          € {{ liveAccountBalance.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
          <div
            class="flex items-center justify-between"
            :class="expectedEndOfMonth >= 0 ? 'text-indigo-300' : 'text-rose-400'"
          >
            <span class="text-slate-400">Verwacht eind</span>
            <span class="font-semibold">
              € {{ expectedEndOfMonth.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="flex items-center justify-between text-slate-400">
            <span>Nog te betalen (vast)</span>
            <span class="font-semibold text-amber-400">
              −€ {{ totalExpenseFixedRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>

      <button
        type="button"
        class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all h-full flex flex-col"
        @click="kpiKey = 'income'"
      >
        <div class="flex items-center justify-between text-slate-400 mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider">
            Inkomsten ({{ currentMonth.monthName }})
          </span>
          <div
            class="w-8 h-8 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400"
          >
            <TrendingUp class="w-4 h-4" />
          </div>
        </div>
        <div class="text-2xl font-black text-emerald-400 font-mono tracking-tight">
          € {{ totalIncomeReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <div class="mt-3 flex items-center justify-between text-xs pt-3 border-t border-slate-800/80">
          <span class="text-slate-400">Begroot:</span>
          <span class="font-mono font-medium text-slate-300">
            € {{ totalIncomeBudget.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
          </span>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>

      <button
        type="button"
        class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all h-full flex flex-col"
        @click="kpiKey = 'expense'"
      >
        <div class="flex items-center justify-between text-slate-400 mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider">
            Uitgaven ({{ currentMonth.monthName }})
          </span>
          <div
            class="w-8 h-8 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-rose-400"
          >
            <TrendingDown class="w-4 h-4" />
          </div>
        </div>
        <div class="text-2xl font-black text-rose-400 font-mono tracking-tight">
          € {{ totalExpensePaid.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Vast begroot</span>
            <span class="font-medium text-slate-300">
              € {{ totalExpenseFixedBudget.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Binnen begroting</span>
            <span class="font-medium text-rose-300">
              € {{ totalExpenseFixedPaid.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div
            v-if="totalExpenseRulesBudget > 0 || totalExpenseOver > 0"
            class="mt-2 pt-1.5 border-t border-slate-800/70 space-y-1"
          >
            <div
              v-if="totalExpenseRulesBudget > 0"
              class="flex items-center justify-between text-indigo-300/90"
            >
              <span>Buiten budget (regels)</span>
              <span>
                € {{ totalExpenseRulesBudget.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
            <div
              v-if="totalExpenseOver > 0"
              class="flex items-center justify-between"
            >
              <span class="text-slate-400">Overschrijding</span>
              <span class="font-medium text-rose-400">
                € {{ totalExpenseOver.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>

      <button
        type="button"
        class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all h-full flex flex-col"
        @click="kpiKey = 'netto'"
      >
        <div class="flex items-center justify-between text-slate-400 mb-3">
          <span class="text-xs font-semibold uppercase tracking-wider">Netto Overschot / Saldo</span>
          <span class="text-[10px] font-semibold text-indigo-400 uppercase tracking-wide">Werkelijk</span>
        </div>
        <div
          class="text-2xl font-black font-mono tracking-tight"
          :class="netActual >= 0 ? 'text-emerald-400' : 'text-rose-400'"
          title="Ontvangen − betaald − gespaard"
        >
          {{ netActual >= 0 ? "+" : "" }}€
          {{ netActual.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <div class="mt-3 space-y-1 text-[11px] font-mono pt-3 border-t border-slate-800/80">
          <div
            v-if="isCurrentReportingMonth"
            class="flex items-center justify-between text-slate-300"
          >
            <span class="text-slate-400">Huidig saldo</span>
            <span>€ {{ periodStartBalance.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}</span>
          </div>
          <div
            class="flex items-center justify-between"
            :class="expectedEndOfMonth >= 0 ? 'text-indigo-300' : 'text-rose-400'"
          >
            <span class="text-slate-400">Verwacht eind</span>
            <span>€ {{ expectedEndOfMonth.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Inkomsten vs begroting</span>
            <span :class="totalIncomeDelta >= 0 ? 'text-emerald-400' : 'text-rose-400'">
              {{ totalIncomeDelta >= 0 ? "+" : "" }}€
              {{ totalIncomeDelta.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Uitgaven vs begroting</span>
            <span :class="totalExpenseDelta <= 0 ? 'text-emerald-400' : 'text-rose-400'">
              {{ totalExpenseDelta >= 0 ? "+" : "" }}€
              {{ totalExpenseDelta.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="flex items-center justify-between text-slate-300">
            <span class="text-slate-400">Netto vs begroting</span>
            <span :class="netBudgetDelta >= 0 ? 'text-emerald-400' : 'text-rose-400'">
              {{ netBudgetDelta >= 0 ? "+" : "" }}€
              {{ netBudgetDelta.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <div>
            <h3 class="font-bold text-white text-base">Jaarlijkse Cashflow & Begroting 2026</h3>
            <p class="text-xs text-slate-400">
              {{
                chartMode === "actual"
                  ? "Werkelijke bankmutaties per maand — maanden zonder mutaties blijven leeg"
                  : "Gepland budget per maand (zelfde template tot je een maand aanpast)"
              }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700">
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
                :class="chartMode === 'actual' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'"
                @click="chartMode = 'actual'"
              >
                Werkelijk
              </button>
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
                :class="chartMode === 'budget' ? 'bg-slate-600 text-white' : 'text-slate-400 hover:text-white'"
                @click="chartMode = 'budget'"
              >
                Begroot
              </button>
            </div>
            <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700">
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
                :class="
                  chartView === 'incomeExpense'
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-400 hover:text-white'
                "
                @click="chartView = 'incomeExpense'"
              >
                Inkomsten vs Uitgaven
              </button>
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
                :class="
                  chartView === 'netCashflow'
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-400 hover:text-white'
                "
                @click="chartView = 'netCashflow'"
              >
                Netto Verloop
              </button>
            </div>
          </div>
        </div>

        <div v-if="chartView === 'incomeExpense'" class="h-72 w-full flex items-end gap-1.5 sm:gap-2 pt-4">
          <div
            v-for="d in annualChartData"
            :key="d.month"
            class="flex-1 flex flex-col items-center gap-1 min-w-0 h-full justify-end"
            :title="`${d.fullName}: Inkomsten €${d.Inkomsten}, Uitgaven €${d.Uitgaven}, Sparen €${d.Sparen}`"
          >
            <div class="w-full flex-1 flex items-end justify-center gap-0.5">
              <div
                class="w-[28%] max-w-[12px] bg-emerald-500 rounded-t"
                :style="{ height: barPct(d.Inkomsten) }"
              />
              <div
                class="w-[28%] max-w-[12px] bg-rose-500 rounded-t"
                :style="{ height: barPct(d.Uitgaven) }"
              />
              <div
                class="w-[28%] max-w-[12px] bg-blue-500 rounded-t"
                :style="{ height: barPct(d.Sparen) }"
              />
            </div>
            <span class="text-[10px] text-slate-400 font-medium">{{ d.month }}</span>
          </div>
        </div>
        <div v-else class="h-72 w-full flex items-end gap-1.5 sm:gap-2 pt-4">
          <div
            v-for="d in annualChartData"
            :key="d.month"
            class="flex-1 flex flex-col items-center gap-1 min-w-0 h-full justify-end"
            :title="`${d.fullName}: Netto €${d.Netto}`"
          >
            <div class="w-full flex-1 flex items-end justify-center">
              <div
                class="w-3/5 max-w-[16px] rounded-t"
                :class="d.Netto >= 0 ? 'bg-indigo-500' : 'bg-rose-500'"
                :style="{ height: barPct(d.Netto) }"
              />
            </div>
            <span class="text-[10px] text-slate-400 font-medium">{{ d.month }}</span>
          </div>
        </div>
        <div
          v-if="chartView === 'incomeExpense'"
          class="flex items-center justify-center gap-4 mt-3 text-[11px] text-slate-400"
        >
          <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500" /> Inkomsten</span>
          <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-rose-500" /> Uitgaven</span>
          <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-blue-500" /> Sparen</span>
        </div>
        <div v-else class="flex items-center justify-center gap-4 mt-3 text-[11px] text-slate-400">
          <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-indigo-500" /> Netto Resultaat</span>
        </div>
      </div>

      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
        <div>
          <div class="flex items-center justify-between mb-3">
            <div class="flex items-center gap-2">
              <Clock class="w-4 h-4 text-amber-400" />
              <h3 class="font-bold text-white text-base">
                Nog te Betalen ({{ currentMonth.monthName }})
              </h3>
            </div>
            <span
              class="text-xs bg-amber-500/10 text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded-full font-mono font-medium"
            >
              {{ unpaidExpenses.length }} posten
            </span>
          </div>
          <p class="text-xs text-slate-400 mb-3">
            Overzicht van geplande vaste lasten die deze maand nog afgeschreven moeten worden.
          </p>

          <div class="space-y-2 max-h-64 overflow-y-auto pr-1">
            <div
              v-if="unpaidExpenses.length === 0"
              class="p-6 text-center text-slate-400 bg-slate-800/40 rounded-xl border border-slate-800"
            >
              <CheckCircle class="w-8 h-8 text-emerald-400 mx-auto mb-2" />
              <p class="text-sm font-medium text-white">Alle rekeningen zijn voldaan!</p>
              <p class="text-xs text-slate-400 mt-1">
                Geen openstaande posten voor {{ currentMonth.monthName }}.
              </p>
            </div>
            <div
              v-for="item in unpaidExpenses"
              :key="item.id"
              class="p-3 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/60 flex items-center justify-between gap-3 transition-colors"
            >
              <div class="overflow-hidden">
                <p class="text-xs font-semibold text-white truncate">{{ item.name }}</p>
                <span class="text-[10px] text-slate-400">{{ item.group }}</span>
              </div>
              <div class="flex items-center gap-2 shrink-0">
                <span class="text-xs font-bold font-mono text-amber-400">
                  €
                  {{
                    (budgetAmount(item) - paidAmount(item)).toLocaleString("nl-NL", {
                      minimumFractionDigits: 2,
                    })
                  }}
                </span>
                <span class="text-[10px] font-medium bg-slate-700/60 text-slate-300 px-2 py-0.5 rounded-lg">
                  In afwachting
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-slate-800 flex items-center justify-between text-xs">
          <span class="text-slate-400 font-medium">Totaal openstaand:</span>
          <span class="font-mono font-bold text-amber-400 text-sm">
            € {{ totalExpenseRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
          </span>
        </div>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="font-bold text-white text-base">Budget vs Werkelijk per Hoofdcategorie</h3>
          <p class="text-xs text-slate-400">
            Bekijk de bestedingen per rubriek voor {{ currentMonth.monthName }}
          </p>
        </div>
        <button
          type="button"
          class="text-xs text-indigo-400 hover:text-indigo-300 font-medium flex items-center gap-1"
          @click="onNavigateTab('categorieen')"
        >
          <span>Alle Categorieën</span>
          <ArrowUpRight class="w-3.5 h-3.5" />
        </button>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div
          v-for="cat in categoryGroups"
          :key="cat.name"
          class="p-4 bg-slate-800/50 hover:bg-slate-800/80 rounded-xl border border-slate-700/60 transition-all"
        >
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-slate-700/80 flex items-center justify-center text-indigo-400">
                <component :is="cat.icon" class="w-4 h-4" />
              </div>
              <div>
                <h4 class="text-xs font-bold text-white">{{ cat.name }}</h4>
                <p class="text-[10px] text-slate-400">{{ cat.items.length }} posten</p>
              </div>
            </div>
            <span
              class="text-xs font-mono font-bold"
              :class="catStats(cat).isOverBudget ? 'text-rose-400' : 'text-slate-300'"
            >
              {{ catStats(cat).percentage }}%
            </span>
          </div>

          <div class="w-full bg-slate-700/60 h-2 rounded-full overflow-hidden my-2.5">
            <div
              class="h-full rounded-full transition-all"
              :class="
                catStats(cat).isOverBudget
                  ? 'bg-rose-500'
                  : cat.type === 'inkomsten'
                    ? 'bg-emerald-500'
                    : cat.type === 'sparen'
                      ? 'bg-blue-500'
                      : 'bg-indigo-500'
              "
              :style="{ width: `${catStats(cat).percentage}%` }"
            />
          </div>

          <div class="flex items-center justify-between text-[11px] font-mono text-slate-400">
            <span>Betaald: €{{ catStats(cat).groupPaid.toFixed(2) }}</span>
            <span>Begroot: €{{ catStats(cat).groupEstimated.toFixed(2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="font-bold text-white text-base">Laatste Live Banktransacties (ING Bank)</h3>
          <p class="text-xs text-slate-400">
            Realtime mutaties automatisch gekoppeld via EnableBanking PSD2
          </p>
        </div>
        <button
          id="dashboard-all-transactions-btn"
          type="button"
          class="text-xs bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl border border-slate-700 font-medium transition-colors"
          @click="onNavigateTab('transacties')"
        >
          Bekijk alle transacties ({{ transactions.length }})
        </button>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
              <th class="py-2.5 px-3">Datum & Tijd</th>
              <th class="py-2.5 px-3">Omschrijving</th>
              <th class="py-2.5 px-3">Categorie</th>
              <th class="py-2.5 px-3">Type</th>
              <th class="py-2.5 px-3 text-right">Bedrag</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/60">
            <tr
              v-for="tx in transactions.slice(0, 6)"
              :key="tx.id"
              class="hover:bg-slate-800/40 transition-colors"
            >
              <td class="py-3 px-3 whitespace-nowrap">
                <TransactionDate :date="tx.date" :time="tx.time" />
              </td>
              <td class="py-3 px-3">
                <p class="font-medium text-white max-w-md truncate">{{ tx.description }}</p>
                <span v-if="tx.counterparty" class="text-[10px] text-slate-400">{{ tx.counterparty }}</span>
              </td>
              <td class="py-3 px-3">
                <span
                  class="inline-block bg-slate-800 text-slate-300 px-2 py-0.5 rounded-md border border-slate-700 text-[11px] font-medium"
                >
                  {{ tx.categoryGroup }}
                </span>
              </td>
              <td class="py-3 px-3">
                <span
                  class="text-[10px] font-bold uppercase px-2 py-0.5 rounded"
                  :class="
                    tx.amount > 0
                      ? 'bg-emerald-950/60 text-emerald-400 border border-emerald-800/50'
                      : tx.type === 'Sparen'
                        ? 'bg-blue-950/60 text-blue-400 border border-blue-800/50'
                        : 'bg-rose-950/60 text-rose-400 border border-rose-800/50'
                  "
                >
                  {{ tx.type }}
                </span>
              </td>
              <td
                class="py-3 px-3 text-right font-mono font-bold text-sm whitespace-nowrap"
                :class="tx.amount > 0 ? 'text-emerald-400' : 'text-rose-400'"
              >
                {{ tx.amount > 0 ? "+" : "" }}€
                {{ Math.abs(tx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <KpiBreakdownModal
      :is-open="Boolean(kpiBreakdown)"
      :title="kpiBreakdown?.title ?? ''"
      :formula="kpiBreakdown?.formula ?? ''"
      :subtitle="kpiBreakdown?.subtitle"
      :columns="kpiBreakdown?.columns ?? []"
      :rows="kpiBreakdown?.rows ?? []"
      :total-value="kpiBreakdown?.totalValue ?? 0"
      :total-label="kpiBreakdown?.totalLabel"
      :total-color-class="kpiBreakdown?.totalColorClass"
      :on-close="() => (kpiKey = null)"
    />
  </div>
</template>
