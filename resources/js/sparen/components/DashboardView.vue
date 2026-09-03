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
  Filter,
  Layers,
  LayoutDashboard,
} from "lucide-vue-next";
import type { MonthlyBudget, Transaction, BankAccount, ActiveTab, BudgetItem, SavingsGoal } from "../types";
import TransactionDate from "./TransactionDate.vue";
import KpiBreakdownModal from "./KpiBreakdownModal.vue";
import CashflowTransactionsModal from "./CashflowTransactionsModal.vue";
import { defaultReportingMonth, formatReportingPeriodShort, reportingPeriodForMonth } from "../month";
import {
  buildBalanceModalBreakdown,
  buildBudgetExpenseModalBreakdown,
  buildBudgetIncomeModalBreakdown,
  buildNettoModalBreakdown,
  computeMonthKpi,
  kpiFromMonthlyBudget,
  isActiveReportingMonth,
  resolvePeriodStartBalance,
} from "../monthKpi";
import { potsNeedingCompensation } from "../potSettlement";
import {
  budgetVsBankRows,
  cashflowBucketTransactions,
  checkingBalanceSeries,
  compactEuro,
  computePeriodCashflow,
  groceriesPinSpent,
  monthEndBalances,
  type CashflowBucket,
} from "../cashflow";
import { formulaRows } from "../kpiBreakdown";

type DashboardKpiKey = "balance" | "income" | "expense" | "netto" | "cashflow";

const props = defineProps<{
  currentMonth: MonthlyBudget;
  allMonths: MonthlyBudget[];
  transactions: Transaction[];
  bankAccount: BankAccount;
  savingsGoals?: SavingsGoal[];
  onNavigateTab: (tab: ActiveTab) => void;
  onOpenUnlinked?: () => void;
}>();

const chartView = ref<"incomeExpense" | "netCashflow">("incomeExpense");
const chartMode = ref<"plan" | "bank">("bank");
const kpiKey = ref<DashboardKpiKey | null>(null);
const txModal = ref<{
  bucket: CashflowBucket;
  title: string;
  subtitle?: string;
  openUnlinked?: boolean;
} | null>(null);

function euro(n: number): string {
  return n.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

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

const monthKpi = computed(() =>
  computeMonthKpi({
    incomeItems: incomeItems.value,
    expenseItems: expenseItems.value,
    savingsItems: savingsItems.value,
    bankBalance: periodStartBalance.value,
  })
);

const potCompensationNeeds = computed(() =>
  potsNeedingCompensation(
    props.currentMonth,
    props.transactions,
    props.savingsGoals ?? []
  )
);

const totalToCompensate = computed(() =>
  potCompensationNeeds.value.reduce((sum, row) => sum + row.shortfall, 0)
);

const totalIncomeBudget = computed(() => monthKpi.value.totalIncomeBudget);
const totalIncomeReceived = computed(() => monthKpi.value.totalIncomeReceived);
const totalIncomeRemaining = computed(() => monthKpi.value.totalIncomeRemaining);
const totalIncomeOver = computed(() => monthKpi.value.totalIncomeOver);
const totalExpenseBudget = computed(() => monthKpi.value.totalExpenseBudget);
const totalExpensePaid = computed(() => monthKpi.value.totalExpensePaid);
const totalExpenseOver = computed(() => monthKpi.value.totalExpenseOver);
const totalExpenseRemaining = computed(() => monthKpi.value.totalExpenseRemaining);
const totalSavingsRemaining = computed(() => monthKpi.value.totalSavingsRemaining);
const expectedEndOfMonth = computed(() => monthKpi.value.expectedEndOfMonth);

const periodCashflow = computed(() =>
  computePeriodCashflow(
    props.transactions,
    props.currentMonth,
    props.savingsGoals ?? []
  )
);

const budgetBankRows = computed(() =>
  budgetVsBankRows(props.currentMonth, props.transactions, props.savingsGoals ?? [])
);

const groceriesPin = computed(() =>
  groceriesPinSpent(props.currentMonth, props.transactions, props.savingsGoals ?? [])
);

const periodLabel = computed(() =>
  formatReportingPeriodShort(reportingPeriodForMonth(props.currentMonth))
);

const balancePoints = computed(() =>
  checkingBalanceSeries({
    transactions: props.transactions,
    month: props.currentMonth,
    liveBalance: liveAccountBalance.value,
    isCurrentMonth: isCurrentReportingMonth.value,
    fallbackStartBalance: props.currentMonth.opRekening ?? 0,
  })
);

const balanceChart = computed(() => {
  const pts = balancePoints.value;
  const w = 640;
  const h = 220;
  const pad = { l: 44, r: 24, t: 20, b: 28 };
  const innerW = w - pad.l - pad.r;
  const innerH = h - pad.t - pad.b;
  if (pts.length === 0) {
    return null;
  }
  const values = pts.map((p) => p.balance);
  const min = Math.min(0, ...values);
  const max = Math.max(1, ...values);
  const span = max - min || 1;
  const xy = pts.map((p, i) => {
    const x = pad.l + (pts.length === 1 ? innerW / 2 : (i / (pts.length - 1)) * innerW);
    const y = pad.t + (1 - (p.balance - min) / span) * innerH;
    return { ...p, x, y };
  });
  const path = xy.map((p, i) => `${i === 0 ? "M" : "L"}${p.x.toFixed(1)} ${p.y.toFixed(1)}`).join(" ");
  const last = xy[xy.length - 1];
  const area = `${path} L${last.x.toFixed(1)} ${(pad.t + innerH).toFixed(1)} L${pad.l} ${(pad.t + innerH).toFixed(1)} Z`;
  const mid = min + span / 2;
  return {
    w,
    h,
    path,
    area,
    last,
    yTicks: [
      { y: pad.t, label: max },
      { y: pad.t + innerH / 2, label: mid },
      { y: pad.t + innerH, label: min },
    ],
    startLabel: pts[0]?.date,
    endLabel: last?.date,
  };
});

const yearBalances = computed(() =>
  monthEndBalances(
    props.allMonths,
    reportingAnchor.value,
    liveAccountBalance.value,
    props.transactions
  )
);

const yearBalanceMax = computed(() =>
  Math.max(1, ...yearBalances.value.filter((row) => !row.isFuture).map((row) => Math.abs(row.balance)))
);

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
    if (chartMode.value === "plan") {
      const kpi = kpiFromMonthlyBudget(m);
      return {
        month: monthLabel(m),
        fullName: m.monthName,
        Inkomsten: Math.round(kpi.totalIncomeBudget),
        Uitgaven: Math.round(kpi.totalExpenseBudget),
        Sparen: Math.round(kpi.totalSavingsBudget),
        Netto: Math.round(kpi.netBudget),
      };
    }

    const flow = computePeriodCashflow(props.transactions, m, props.savingsGoals ?? []);
    return {
      month: monthLabel(m),
      fullName: m.monthName,
      Inkomsten: Math.round(flow.received),
      Uitgaven: Math.round(flow.spent),
      Sparen: Math.round(flow.toSavings),
      Netto: Math.round(flow.netFromAccount),
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
    return buildBudgetIncomeModalBreakdown(incomeItems.value, kpi);
  }
  if (key === "expense") {
    return buildBudgetExpenseModalBreakdown(expenseItems.value, kpi);
  }
  if (key === "cashflow") {
    const flow = periodCashflow.value;
    const { columns, rows } = formulaRows([
      { id: "inc", label: "Echt ontvangen", amount: flow.received, tone: "plus" },
      { id: "exp", label: "− Echt uitgegeven", amount: -flow.spent, tone: "minus" },
      { id: "sav", label: "− Naar spaar − van spaar", amount: -flow.netSavings, tone: "minus" },
      { id: "net", label: "= Netto van de rekening", amount: flow.netFromAccount, tone: "result" },
    ]);
    return {
      title: "Cashflow-check",
      formula: "Ontvangen − uitgeven − netto sparen",
      subtitle: "Elke euro op de betaalrekening deze rapportageperiode",
      columns,
      rows,
      totalValue: flow.netFromAccount,
      totalLabel: "Netto van de rekening",
      totalColorClass: flow.netFromAccount >= 0 ? "text-emerald-400" : "text-rose-400",
    };
  }
  return buildNettoModalBreakdown(kpi, {
    mode: "budget",
    bankBalance: periodStartBalance.value,
    includeStartBalance: isCurrentReportingMonth.value,
  });
});

function barPct(value: number) {
  return `${Math.max(2, Math.round((Math.abs(value) / chartMax.value) * 100))}%`;
}

function yearBalancePct(value: number) {
  return `${Math.max(4, Math.round((Math.abs(value) / yearBalanceMax.value) * 100))}%`;
}

function isoDayMonth(iso: string): string {
  const [, month, day] = iso.slice(0, 10).split("-");
  const labels = ["jan", "feb", "mrt", "apr", "mei", "jun", "jul", "aug", "sep", "okt", "nov", "dec"];
  return `${Number(day)} ${labels[Number(month) - 1] ?? ""}`;
}

function openCashflowBucket(
  bucket: CashflowBucket,
  title: string,
  subtitle?: string,
  openUnlinked = false
) {
  txModal.value = { bucket, title, subtitle, openUnlinked };
}

const txModalList = computed(() =>
  txModal.value
    ? cashflowBucketTransactions(periodCashflow.value, txModal.value.bucket)
    : []
);

function handleOpenUnlinkedFromModal() {
  txModal.value = null;
  if (props.onOpenUnlinked) {
    props.onOpenUnlinked();
    return;
  }
  props.onNavigateTab("transacties");
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
    <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
      <div class="flex items-center gap-2">
        <LayoutDashboard class="w-5 h-5 text-indigo-400" />
        <h2 class="text-xl font-bold text-white tracking-tight">
          Dashboard • {{ currentMonth.monthName }} {{ currentMonth.year }}
        </h2>
      </div>
      <p class="text-xs text-slate-400 mt-1">
        Plan (begroting) en werkelijkheid (cashflow) voor {{ periodLabel }}
      </p>
    </div>

    <section class="space-y-3">
      <div>
        <h3 class="font-bold text-white text-base">Begroting deze periode</h3>
        <p class="text-xs text-slate-400 mt-0.5">
          Het plan: wat is begroot, wat is op de posten ontvangen of betaald, en wat je overhoudt als
          alles netjes binnenkomt en je geen extra uitgaven doet.
        </p>
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
            <span>Nog te betalen</span>
            <span class="font-semibold text-amber-400">
              −€ {{ totalExpenseRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div
            v-if="totalToCompensate > 0"
            class="flex items-center justify-between text-amber-300"
          >
            <span>Nog te compenseren</span>
            <span class="font-semibold">
              +€ {{ totalToCompensate.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
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
          € {{ totalIncomeBudget.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
          <div class="flex items-center justify-between text-emerald-400">
            <span class="text-slate-400">Ontvangen</span>
            <span class="font-medium">
              € {{ totalIncomeReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div
            class="flex items-center justify-between"
            :class="totalIncomeRemaining > 0 ? 'text-amber-400' : 'text-slate-500'"
          >
            <span class="text-slate-400">Nog te ontvangen</span>
            <span class="font-medium">
              € {{ totalIncomeRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div
            v-if="totalIncomeOver > 0"
            class="flex items-center justify-between pt-1.5 mt-1 border-t border-slate-800/70 text-emerald-400"
          >
            <span class="text-slate-400">Meer ontvangen</span>
            <span class="font-medium">
              +€ {{ totalIncomeOver.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
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
          € {{ totalExpenseBudget.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Betaald</span>
            <span class="font-medium text-rose-300">
              € {{ totalExpensePaid.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="flex items-center justify-between text-amber-400">
            <span class="text-slate-400">Nog te betalen</span>
            <span class="font-medium">
              € {{ totalExpenseRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div
            v-if="totalToCompensate > 0"
            class="flex items-center justify-between text-amber-300"
          >
            <span class="text-slate-400">Nog te compenseren</span>
            <span class="font-medium">
              € {{ totalToCompensate.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div
            v-if="totalExpenseOver > 0"
            class="mt-2 pt-1.5 border-t border-slate-800/70"
          >
            <div class="flex items-center justify-between">
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
          <span class="text-[10px] font-semibold text-indigo-400 uppercase tracking-wide">Verwacht eind</span>
        </div>
        <div
          class="text-2xl font-black font-mono tracking-tight"
          :class="expectedEndOfMonth >= 0 ? 'text-white' : 'text-rose-400'"
          title="Huidig saldo + nog te ontvangen − nog te betalen − nog te sparen"
        >
          € {{ expectedEndOfMonth.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
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
            v-if="totalIncomeRemaining > 0"
            class="flex items-center justify-between text-emerald-400"
          >
            <span class="text-slate-400">Nog te ontvangen</span>
            <span>+€ {{ totalIncomeRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}</span>
          </div>
          <div class="flex items-center justify-between text-rose-400">
            <span class="text-slate-400">Nog te betalen</span>
            <span>−€ {{ totalExpenseRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}</span>
          </div>
          <div class="flex items-center justify-between text-blue-400">
            <span class="text-slate-400">Nog te sparen</span>
            <span>−€ {{ totalSavingsRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}</span>
          </div>
          <div
            v-if="totalToCompensate > 0"
            class="flex items-center justify-between text-amber-300"
          >
            <span class="text-slate-400">Nog te compenseren</span>
            <span>+€ {{ totalToCompensate.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}</span>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>
    </div>
    </section>

    <section id="dashboard-cashflow" class="space-y-3">
      <div>
        <h3 class="font-bold text-white text-base">Cashflow deze periode</h3>
        <p class="text-xs text-slate-400 mt-0.5">
          De bank: elke euro die de betaalrekening op of af ging. Spaaroverboekingen staan apart, niet
          bij uitgaven. Ook ongekoppelde mutaties tellen mee. Klik een kaart voor de mutaties.
        </p>
      </div>
      <button
        v-if="periodCashflow.unlinkedSpent > 0"
        type="button"
        class="w-full flex items-center justify-between gap-3 text-left px-4 py-3 rounded-xl bg-amber-950/40 border border-amber-800/60 hover:border-amber-600"
        @click="openCashflowBucket('unlinked', 'Ongekoppeld deze periode', 'Mutaties zonder begrotingspost', true)"
      >
        <span class="text-sm text-amber-100">
          {{ periodCashflow.transactions.unlinked.length }} mutaties niet gekoppeld
        </span>
        <span class="font-mono font-bold text-amber-300">€ {{ euro(periodCashflow.unlinkedSpent) }}</span>
        <span class="text-xs font-semibold text-white bg-amber-700 px-2 py-1 rounded-lg">Koppelen</span>
      </button>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-stretch">
        <button
          type="button"
          class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm h-full flex flex-col transition-all"
          @click="openCashflowBucket('received', 'Echt ontvangen', 'Bijschrijvingen, zonder spaaropnames')"
        >
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Echt ontvangen</span>
          <div class="text-2xl font-black text-emerald-400 font-mono tracking-tight mt-2">
            € {{ euro(periodCashflow.received) }}
          </div>
          <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
            <div class="flex items-center justify-between text-emerald-400">
              <span class="text-slate-400">Salaris, toeslagen, overig +</span>
              <span class="font-medium">€ {{ euro(periodCashflow.received) }}</span>
            </div>
          </div>
          <p class="mt-auto pt-2 text-[10px] text-slate-500">Klik voor mutaties</p>
        </button>
        <button
          type="button"
          class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm h-full flex flex-col transition-all"
          @click="openCashflowBucket('spent', 'Echt uitgegeven', 'Pin, incasso, iDEAL — geen spaaroverboekingen')"
        >
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Echt uitgegeven</span>
          <div class="text-2xl font-black text-rose-400 font-mono tracking-tight mt-2">
            € {{ euro(periodCashflow.spent) }}
          </div>
          <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Pin, incasso, iDEAL</span>
              <span class="font-medium text-rose-300">€ {{ euro(periodCashflow.spent) }}</span>
            </div>
            <div v-if="groceriesPin > 0" class="flex items-center justify-between text-slate-400">
              <span>Waarvan boodschappen pin</span>
              <span class="font-medium text-slate-200">€ {{ euro(groceriesPin) }}</span>
            </div>
          </div>
          <p class="mt-auto pt-2 text-[10px] text-slate-500">Klik voor mutaties</p>
        </button>
        <button
          type="button"
          class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm h-full flex flex-col transition-all"
          @click="openCashflowBucket('toSavings', 'Naar spaargeld', 'Stortingen vanaf de betaalrekening')"
        >
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Naar spaargeld</span>
          <div class="text-2xl font-black text-blue-400 font-mono tracking-tight mt-2">
            € {{ euro(periodCashflow.toSavings) }}
          </div>
          <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Spaardoelen</span>
              <span class="font-medium text-blue-300">€ {{ euro(periodCashflow.toSavingsGoals) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Potjes</span>
              <span class="font-medium text-slate-200">€ {{ euro(periodCashflow.toSavingsPots) }}</span>
            </div>
          </div>
          <p class="mt-auto pt-2 text-[10px] text-slate-500">Klik voor mutaties</p>
        </button>
        <button
          type="button"
          class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm h-full flex flex-col transition-all"
          @click="openCashflowBucket('fromSavings', 'Van spaargeld', 'Opnames en potcompensatie terug op de betaalrekening')"
        >
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Van spaargeld</span>
          <div class="text-2xl font-black text-amber-400 font-mono tracking-tight mt-2">
            € {{ euro(periodCashflow.fromSavings) }}
          </div>
          <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Opnames spaardoelen</span>
              <span class="font-medium text-slate-200">€ {{ euro(periodCashflow.fromSavingsGoals) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Compensatie uit potjes</span>
              <span class="font-medium text-amber-300">€ {{ euro(periodCashflow.fromSavingsPots) }}</span>
            </div>
          </div>
          <p class="mt-auto pt-2 text-[10px] text-slate-500">Klik voor mutaties</p>
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <button
          type="button"
          class="text-left bg-slate-900 border border-slate-800 hover:border-indigo-500/50 p-5 rounded-2xl shadow-sm transition-all"
          @click="kpiKey = 'cashflow'"
        >
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Cashflow-check</span>
          <div class="text-lg font-bold text-white mt-2 tracking-tight">
            Ontvangen − uitgeven − netto sparen
          </div>
          <div class="mt-3 space-y-1 text-xs pt-3 border-t border-slate-800/80 font-mono">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Echt ontvangen</span>
              <span class="font-semibold text-emerald-400">+ € {{ euro(periodCashflow.received) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Echt uitgegeven</span>
              <span class="font-semibold text-rose-400">− € {{ euro(periodCashflow.spent) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Naar spaar − van spaar</span>
              <span class="font-semibold text-blue-400">
                {{ periodCashflow.netSavings >= 0 ? "−" : "+" }}
                € {{ euro(Math.abs(periodCashflow.netSavings)) }}
              </span>
            </div>
            <div class="flex items-center justify-between pt-1.5 mt-1 border-t border-slate-800/70">
              <span class="text-slate-400">Netto van de rekening</span>
              <span
                class="font-semibold"
                :class="periodCashflow.netFromAccount >= 0 ? 'text-white' : 'text-rose-400'"
              >
                {{ periodCashflow.netFromAccount >= 0 ? "+" : "−" }}
                € {{ euro(Math.abs(periodCashflow.netFromAccount)) }}
              </span>
            </div>
          </div>
          <p class="mt-3 text-[10px] text-slate-500">
            Dit moet (ongeveer) het verschil in betaalsaldo over de periode verklaren.
          </p>
        </button>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Waarom begroting anders is
          </span>
          <table class="w-full text-left text-xs mt-3">
            <thead>
              <tr class="border-b border-slate-800 text-slate-400 font-semibold uppercase tracking-wider">
                <th class="py-2 pr-2">Emmer</th>
                <th class="py-2 px-2 text-right">Begroting</th>
                <th class="py-2 pl-2 text-right">Bank</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/60 font-mono">
              <tr
                v-for="row in budgetBankRows"
                :key="row.id"
                :class="row.bucket ? 'cursor-pointer hover:bg-slate-800/50' : ''"
                @click="
                  row.bucket
                    ? openCashflowBucket(
                        row.bucket,
                        row.label,
                        'Niet gekoppeld aan een begrotingspost',
                        true
                      )
                    : undefined
                "
              >
                <td class="py-2.5 pr-2 font-sans text-slate-200">
                  {{ row.label }}
                  <span v-if="row.bucket" class="text-[10px] text-indigo-400 font-sans ml-1">open</span>
                </td>
                <td class="py-2.5 px-2 text-right text-slate-300">
                  € {{ euro(row.budgetAmount) }}
                  <span v-if="row.budgetHint" class="text-slate-500"> {{ row.budgetHint }}</span>
                </td>
                <td class="py-2.5 pl-2 text-right" :class="row.bankHint === 'pin' ? 'text-rose-400' : row.id === 'unlinked' && row.bankAmount > 0 ? 'text-amber-400' : 'text-slate-200'">
                  € {{ euro(row.bankAmount) }}
                  <span v-if="row.bankHint" class="text-slate-500"> {{ row.bankHint }}</span>
                </td>
              </tr>
            </tbody>
          </table>
          <p class="mt-3 text-[10px] text-slate-500">
            Begroting volgt posten en enveloppen. Bank volgt elke euro op de betaalrekening.
          </p>
        </div>
      </div>
    </section>

    <section class="space-y-3">
      <div>
        <h3 class="font-bold text-white text-base">Saldo op rekening</h3>
        <p class="text-xs text-slate-400 mt-0.5">
          Verloop van de betaalrekening. Links deze rapportagemaand (reconstructie uit mutaties). Rechts
          maandeinde over het jaar.
        </p>
      </div>
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
        <div class="lg:col-span-3 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Deze periode · {{ periodLabel }}
          </span>
          <svg
            v-if="balanceChart"
            class="mt-3 w-full h-56"
            :viewBox="`0 0 ${balanceChart.w} ${balanceChart.h}`"
            role="img"
            aria-label="Saldo deze periode"
          >
            <defs>
              <linearGradient id="dash-balance-area" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#818cf8" stop-opacity="0.35" />
                <stop offset="100%" stop-color="#818cf8" stop-opacity="0" />
              </linearGradient>
            </defs>
            <g stroke="#1e293b" stroke-width="1">
              <line
                v-for="tick in balanceChart.yTicks"
                :key="tick.y"
                x1="44"
                :y1="tick.y"
                x2="616"
                :y2="tick.y"
              />
            </g>
            <text
              v-for="tick in balanceChart.yTicks"
              :key="`l-${tick.y}`"
              x="8"
              :y="tick.y + 4"
              fill="#64748b"
              font-size="11"
            >
              € {{ Math.round(tick.label).toLocaleString("nl-NL") }}
            </text>
            <path :d="balanceChart.area" fill="url(#dash-balance-area)" />
            <path :d="balanceChart.path" fill="none" stroke="#818cf8" stroke-width="2.5" />
            <circle :cx="balanceChart.last.x" :cy="balanceChart.last.y" r="5" fill="#c7d2fe" />
            <text
              :x="Math.max(80, balanceChart.last.x - 90)"
              :y="balanceChart.last.y - 10"
              fill="#c7d2fe"
              font-size="11"
              font-family="ui-monospace, Menlo, monospace"
            >
              nu € {{ euro(balanceChart.last.balance) }}
            </text>
            <text x="44" y="212" fill="#64748b" font-size="11">
              {{ isoDayMonth(balanceChart.startLabel) }}
            </text>
            <text x="560" y="212" fill="#64748b" font-size="11">
              {{ isoDayMonth(balanceChart.endLabel) }}
            </text>
          </svg>
          <p v-else class="text-xs text-slate-500 mt-6">Nog geen mutaties om een saldo-lijn te tekenen.</p>
          <p class="mt-2 text-[10px] text-slate-500">
            Reconstructie vanaf het live saldo en de mutaties in deze periode.
          </p>
        </div>
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
          <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">
            Maandeinde {{ currentMonth.year }}
          </span>
          <div class="h-56 w-full flex items-end gap-1.5 pt-4">
            <div
              v-for="row in yearBalances"
              :key="row.monthId"
              class="flex-1 flex flex-col items-center gap-1 min-w-0 h-full justify-end"
              :class="row.isFuture ? 'opacity-30' : ''"
            >
              <span
                v-if="!row.isFuture && Math.abs(row.balance) > 0"
                class="text-[9px] text-indigo-200/90 font-mono truncate max-w-full"
              >
                {{ compactEuro(row.balance) }}
              </span>
              <div class="w-full flex-1 flex items-end justify-center">
                <div
                  class="w-3/5 max-w-[16px] rounded-t bg-gradient-to-t from-indigo-700 to-indigo-400"
                  :class="row.isCurrent ? 'ring-2 ring-indigo-300 ring-offset-1 ring-offset-slate-900' : ''"
                  :style="{ height: yearBalancePct(row.balance) }"
                />
              </div>
              <span class="text-[10px] text-slate-400 font-medium">{{ row.short }}</span>
            </div>
          </div>
          <p class="mt-2 text-[10px] text-slate-500">
            Huidige maand = live saldo. Gesloten maanden = gereconstrueerd uit mutaties na die periode.
          </p>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
          <div>
            <h3 class="font-bold text-white text-base">
              {{ chartMode === "plan" ? "Plan per maand" : "Bank per maand" }}
              {{ currentMonth.year }}
            </h3>
            <p class="text-xs text-slate-400">
              {{
                chartMode === "plan"
                  ? "Begroot per maand — dezelfde lens als maandbegroting"
                  : "Echt ontvangen / uitgegeven / naar spaar — dezelfde lens als cashflow"
              }}
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-xl border border-slate-700">
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
                :class="chartMode === 'plan' ? 'bg-slate-600 text-white' : 'text-slate-400 hover:text-white'"
                @click="chartMode = 'plan'"
              >
                Plan
              </button>
              <button
                type="button"
                class="text-xs px-3 py-1 rounded-lg font-medium transition-colors"
                :class="chartMode === 'bank' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white'"
                @click="chartMode = 'bank'"
              >
                Bank
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
            class="relative group flex-1 flex flex-col items-center gap-1 min-w-0 h-full justify-end"
          >
            <div
              class="pointer-events-none absolute bottom-full mb-1 z-10 hidden group-hover:block bg-slate-800 border border-slate-600 rounded-lg px-2 py-1.5 text-[10px] whitespace-nowrap shadow-lg"
            >
              <p class="font-semibold text-white mb-0.5">{{ d.fullName }}</p>
              <p class="text-emerald-400">Inkomsten {{ compactEuro(d.Inkomsten) }}</p>
              <p class="text-rose-400">Uitgaven {{ compactEuro(d.Uitgaven) }}</p>
              <p class="text-blue-400">Sparen {{ compactEuro(d.Sparen) }}</p>
            </div>
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
          >
            <span v-if="d.Netto !== 0" class="text-[9px] font-mono truncate max-w-full" :class="d.Netto >= 0 ? 'text-indigo-200' : 'text-rose-300'">
              {{ compactEuro(d.Netto) }}
            </span>
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
    <CashflowTransactionsModal
      :is-open="Boolean(txModal)"
      :title="txModal?.title ?? ''"
      :subtitle="txModal?.subtitle"
      :transactions="txModalList"
      :on-close="() => (txModal = null)"
      :on-open-in-transactions="txModal?.openUnlinked ? handleOpenUnlinkedFromModal : undefined"
    />
  </div>
</template>
