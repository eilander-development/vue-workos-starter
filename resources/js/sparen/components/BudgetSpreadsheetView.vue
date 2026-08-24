<script setup lang="ts">
import { computed, ref, type Component } from "vue";
import {
  ChevronLeft,
  ChevronRight,
  Edit2,
  Check,
  X,
  Plus,
  Calendar,
  Wallet,
  Utensils,
  Home,
  Car,
  Shield,
  PiggyBank,
  Landmark,
  Layers,
  ShoppingBag,
  Search,
  Download,
  Eye,
} from "lucide-vue-next";
import type {
  MonthlyBudget,
  BudgetItem,
  BudgetCategoryGroup,
  Transaction,
  ActiveTab,
  BankAccount,
} from "../types";
import { isTransactionInReportingMonth, formatReportingPeriodLabel, reportingPeriodForMonth, defaultReportingMonth } from "../month";
import { isLinkExcludedTransaction } from "../matchRule";
import { hasPotEnvelope, shadowOverspend } from "../potSettlement";
import TransactionDate from "./TransactionDate.vue";
import KpiBreakdownModal from "./KpiBreakdownModal.vue";
import { sumBudgetedAmount, sumBudgetedPaid, sumBudgetedRemaining, sumBudgetedOver, hasBudget } from "../kpiBreakdown";
import {
  buildBudgetExpenseModalBreakdown,
  buildBudgetIncomeModalBreakdown,
  buildBudgetSavingsModalBreakdown,
  buildNettoModalBreakdown,
  computeMonthKpi,
  isActiveReportingMonth,
  resolvePeriodStartBalance,
} from "../monthKpi";

type SpreadsheetKpiKey = "income" | "expense" | "savings" | "netto";

const props = defineProps<{
  currentMonth: MonthlyBudget;
  allMonths: MonthlyBudget[];
  transactions: Transaction[];
  bankAccount?: BankAccount;
  onSelectMonth: (monthId: string) => void;
  onUpdateBudgetItem: (itemId: string, updates: Partial<BudgetItem>) => void;
  onOpenAddBudgetItem: () => void;
  onOpenEditBudgetItem?: (item: BudgetItem) => void;
  onOpenItemTransactions?: (item: BudgetItem) => void;
  onNavigateTab?: (tab: ActiveTab) => void;
}>();

const euro = (n: number) =>
  n.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

interface CategoryGroupDef {
  title: string;
  groupKey: BudgetCategoryGroup;
  type: "inkomsten" | "uitgaven" | "sparen";
  icon: Component;
  iconBg: string;
  iconColor: string;
  badgeBg: string;
  badgeLabel: string;
}

const CATEGORY_GROUPS: CategoryGroupDef[] = [
  {
    title: "Inkomsten",
    groupKey: "Inkomsten",
    type: "inkomsten",
    icon: Wallet,
    iconBg: "bg-emerald-500/15 border-emerald-500/30",
    iconColor: "text-emerald-400",
    badgeBg: "bg-emerald-500/20 border-emerald-500/40 text-emerald-400",
    badgeLabel: "Inkomsten",
  },
  {
    title: "Dagelijks Leven (Eten & Huishouden)",
    groupKey: "Dagelijks Leven",
    type: "uitgaven",
    icon: Utensils,
    iconBg: "bg-rose-500/15 border-rose-500/30",
    iconColor: "text-rose-400",
    badgeBg: "bg-rose-500/20 border-rose-500/40 text-rose-300",
    badgeLabel: "Uitgaven",
  },
  {
    title: "Woning & Energie",
    groupKey: "Woning",
    type: "uitgaven",
    icon: Home,
    iconBg: "bg-indigo-500/15 border-indigo-500/30",
    iconColor: "text-indigo-400",
    badgeBg: "bg-rose-500/20 border-rose-500/40 text-rose-300",
    badgeLabel: "Uitgaven",
  },
  {
    title: "Vervoersmiddelen & Brandstof",
    groupKey: "Vervoersmiddelen",
    type: "uitgaven",
    icon: Car,
    iconBg: "bg-amber-500/15 border-amber-500/30",
    iconColor: "text-amber-400",
    badgeBg: "bg-rose-500/20 border-rose-500/40 text-rose-300",
    badgeLabel: "Uitgaven",
  },
  {
    title: "Verzekeringen",
    groupKey: "Verzekeringen",
    type: "uitgaven",
    icon: Shield,
    iconBg: "bg-purple-500/15 border-purple-500/30",
    iconColor: "text-purple-400",
    badgeBg: "bg-rose-500/20 border-rose-500/40 text-rose-300",
    badgeLabel: "Uitgaven",
  },
  {
    title: "Spaargeld & Doelen",
    groupKey: "Spaargeld",
    type: "sparen",
    icon: PiggyBank,
    iconBg: "bg-blue-500/15 border-blue-500/30",
    iconColor: "text-blue-400",
    badgeBg: "bg-blue-500/20 border-blue-500/40 text-blue-300",
    badgeLabel: "Sparen",
  },
  {
    title: "Leningen & Hypotheek",
    groupKey: "Leningen",
    type: "uitgaven",
    icon: Landmark,
    iconBg: "bg-cyan-500/15 border-cyan-500/30",
    iconColor: "text-cyan-400",
    badgeBg: "bg-rose-500/20 border-rose-500/40 text-rose-300",
    badgeLabel: "Uitgaven",
  },
  {
    title: "Overige Vaste Kosten",
    groupKey: "Overige Vaste Kosten",
    type: "uitgaven",
    icon: Layers,
    iconBg: "bg-teal-500/15 border-teal-500/30",
    iconColor: "text-teal-400",
    badgeBg: "bg-rose-500/20 border-rose-500/40 text-rose-300",
    badgeLabel: "Uitgaven",
  },
  {
    title: "Overige Kosten & Variabel",
    groupKey: "Overige Kosten",
    type: "uitgaven",
    icon: ShoppingBag,
    iconBg: "bg-slate-500/15 border-slate-500/30",
    iconColor: "text-slate-400",
    badgeBg: "bg-rose-500/20 border-rose-500/40 text-rose-300",
    badgeLabel: "Uitgaven",
  },
];

const selectedGroupModal = ref<CategoryGroupDef | null>(null);
const searchQuery = ref("");
const filterType = ref<"alle" | "inkomsten" | "uitgaven" | "sparen">("alle");
const editingId = ref<string | null>(null);
const editActual = ref(0);
const kpiKey = ref<SpreadsheetKpiKey | null>(null);

const currentIndex = computed(() =>
  props.allMonths.findIndex((m) => m.monthId === props.currentMonth.monthId)
);
const prevMonth = computed(() =>
  currentIndex.value > 0 ? props.allMonths[currentIndex.value - 1] : null
);
const nextMonth = computed(() =>
  currentIndex.value < props.allMonths.length - 1
    ? props.allMonths[currentIndex.value + 1]
    : null
);

const monthTransactions = computed(() =>
  props.transactions.filter(
    (t) =>
      isTransactionInReportingMonth(t, props.currentMonth) &&
      !isLinkExcludedTransaction(t)
  )
);

const currentPeriodLabel = computed(() => {
  const month = props.currentMonth;
  const period =
    month.periodStart && month.periodEnd
      ? { start: month.periodStart, end: month.periodEnd }
      : reportingPeriodForMonth(month);
  return formatReportingPeriodLabel(period);
});

const incomeItems = computed(() => props.currentMonth.items.filter((i) => i.type === "inkomsten"));
const expenseItems = computed(() => props.currentMonth.items.filter((i) => i.type === "uitgaven"));
const savingsItems = computed(() => props.currentMonth.items.filter((i) => i.type === "sparen"));

const reportingAnchor = computed(() => defaultReportingMonth());

const liveAccountBalance = computed(() => {
  if (props.bankAccount && props.bankAccount.status !== "disconnected") {
    return props.bankAccount.balance;
  }
  const anchorMonth = props.allMonths.find(
    (m) => m.monthId === reportingAnchor.value.monthId && m.year === reportingAnchor.value.year
  );
  return anchorMonth?.opRekening ?? props.currentMonth.opRekening ?? 0;
});

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

const totalIncomeBudget = computed(() => monthKpi.value.totalIncomeBudget);
const totalIncomeReceived = computed(() => monthKpi.value.totalIncomeReceived);
const totalIncomeRemaining = computed(() => monthKpi.value.totalIncomeRemaining);
const totalExpenseBudget = computed(() => monthKpi.value.totalExpenseBudget);
const totalExpenseFixedBudget = computed(() => monthKpi.value.totalExpenseFixedBudget);
const totalExpenseRulesBudget = computed(() => monthKpi.value.totalExpenseRulesBudget);
const expenseFixedCount = computed(() => monthKpi.value.expenseFixedCount);
const totalExpenseFixedPaid = computed(() => monthKpi.value.totalExpenseFixedPaid);
const totalExpenseFixedRemaining = computed(() => monthKpi.value.totalExpenseFixedRemaining);
const totalExpensePaid = computed(() => monthKpi.value.totalExpensePaid);
const totalExpenseRemaining = computed(() => monthKpi.value.totalExpenseRemaining);
const totalSavingsBudget = computed(() => monthKpi.value.totalSavingsBudget);
const totalSavingsPaid = computed(() => monthKpi.value.totalSavingsPaid);
const totalSavingsRemaining = computed(() => monthKpi.value.totalSavingsRemaining);
const totalSavingsOver = computed(() => monthKpi.value.totalSavingsOver);
const totalIncomeOver = computed(() => monthKpi.value.totalIncomeOver);
const totalExpenseOver = computed(() => monthKpi.value.totalExpenseOver);
const totalExpenseOutsideBudget = computed(() => monthKpi.value.totalExpenseOutsideBudget);
const totalIncomeBank = computed(() => monthKpi.value.totalIncomeBank);
const totalExpenseBank = computed(() => monthKpi.value.totalExpenseBank);
const totalSavingsBank = computed(() => monthKpi.value.totalSavingsBank);
const actualCashflow = computed(() => monthKpi.value.actualCashflow);
const plannedSurplus = computed(() => monthKpi.value.plannedSurplus);
const plannedSurplusWithRules = computed(() => monthKpi.value.plannedSurplusWithRules);
const expectedEndOfMonth = computed(() => monthKpi.value.expectedEndOfMonth);

function openItemFromKpi(item: BudgetItem) {
  kpiKey.value = null;
  props.onOpenItemTransactions?.(item);
}

const kpiBreakdown = computed(() => {
  const key = kpiKey.value;
  if (!key) return null;
  const openFn = props.onOpenItemTransactions ? openItemFromKpi : undefined;

  if (key === "income") {
    return buildBudgetIncomeModalBreakdown(incomeItems.value, monthKpi.value, openFn);
  }
  if (key === "expense") {
    return buildBudgetExpenseModalBreakdown(expenseItems.value, monthKpi.value, openFn);
  }
  if (key === "savings") {
    return buildBudgetSavingsModalBreakdown(savingsItems.value, monthKpi.value, openFn);
  }
  return buildNettoModalBreakdown(monthKpi.value, {
    mode: "budget",
    bankBalance: periodStartBalance.value,
    includeStartBalance: isCurrentReportingMonth.value,
  });
});

const filteredGroups = computed(() =>
  CATEGORY_GROUPS.filter((grp) => {
    if (filterType.value !== "alle" && grp.type !== filterType.value) return false;
    if (!searchQuery.value) return true;
    const q = searchQuery.value.toLowerCase();
    if (grp.title.toLowerCase().includes(q)) return true;
    const items = props.currentMonth.items.filter((i) => i.group === grp.groupKey);
    return items.some((i) => i.name.toLowerCase().includes(q));
  })
);

function groupItems(grp: CategoryGroupDef) {
  return props.currentMonth.items.filter((i) => i.group === grp.groupKey);
}

function groupTotals(grp: CategoryGroupDef) {
  const items = groupItems(grp);
  const totalBudget = sumBudgetedAmount(items);
  const totalPaidOrReceived = sumBudgetedPaid(items);
  const totalPaymentCount = items.reduce(
    (s, i) => s + (hasBudget(i) ? paymentCount(i) : 0),
    0
  );
  const incomeSurplus = grp.type === "inkomsten" ? sumBudgetedOver(items) : 0;
  const incomeRemaining = grp.type === "inkomsten" ? sumBudgetedRemaining(items) : 0;
  const expenseOverpaid = grp.type !== "inkomsten" ? sumBudgetedOver(items) : 0;
  const expenseRemaining = grp.type !== "inkomsten" ? sumBudgetedRemaining(items) : 0;
  return {
    items,
    totalBudget,
    totalPaidOrReceived,
    totalPaymentCount,
    incomeSurplus,
    incomeRemaining,
    expenseOverpaid,
    expenseRemaining,
  };
}

function handleStartEdit(item: BudgetItem) {
  editingId.value = item.id;
  editActual.value = item.actual;
}

function handleSaveEdit(itemId: string) {
  props.onUpdateBudgetItem(itemId, { actual: editActual.value });
  editingId.value = null;
}

function openItemName(item: BudgetItem) {
  if (props.onOpenItemTransactions) props.onOpenItemTransactions(item);
  else if (props.onOpenEditBudgetItem) props.onOpenEditBudgetItem(item);
  else handleStartEdit(item);
}

function handleExportCSV() {
  let csv = "Categoriegroep,Post,Betalingen,Budget,Ontvangen / Betaald,Nog openstaand\n";
  props.currentMonth.items.forEach((item) => {
    const pending =
      item.type === "inkomsten"
        ? item.paidOrReceived >= item.actual
          ? `+${(item.paidOrReceived - item.actual).toFixed(2)}`
          : (item.actual - item.paidOrReceived).toFixed(2)
        : item.paidOrReceived > item.actual
          ? `-${(item.paidOrReceived - item.actual).toFixed(2)}`
          : (item.actual - item.paidOrReceived).toFixed(2);
    csv += `"${item.group}","${item.name}",${item.paymentCount || 0},${item.actual.toFixed(2)},${item.paidOrReceived.toFixed(2)},"${pending}"\n`;
  });
  const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute(
    "download",
    `Maandbegroting-${props.currentMonth.monthName}-${props.currentMonth.year}.csv`
  );
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

function paymentCount(item: BudgetItem) {
  return item.paymentCount ?? (item.paidOrReceived > 0 ? 1 : 0);
}

function cardId(groupKey: string) {
  return `card-budget-${groupKey.toLowerCase().replace(/\s+/g, "-")}`;
}
</script>

<template>
  <div id="budget-cards-view" class="space-y-6">
    <div
      class="bg-slate-900/90 border border-slate-800 p-4 rounded-2xl shadow-sm flex flex-wrap items-center justify-between gap-4"
    >
      <div class="flex items-center gap-3">
        <div class="flex items-center bg-slate-800/90 rounded-xl p-1 border border-slate-700">
          <button
            type="button"
            :disabled="!prevMonth"
            class="p-1.5 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors rounded-lg"
            title="Vorige maand"
            @click="prevMonth && onSelectMonth(prevMonth.monthId)"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <div class="px-3 py-1 flex flex-col">
            <div class="flex items-center gap-2">
              <Calendar class="w-4 h-4 text-indigo-400" />
              <span class="font-bold text-white text-sm font-sans tracking-wide">
                {{ currentMonth.monthName }} {{ currentMonth.year }}
              </span>
            </div>
            <span class="text-[11px] text-slate-400 pl-6">{{ currentPeriodLabel }}</span>
          </div>
          <button
            type="button"
            :disabled="!nextMonth"
            class="p-1.5 text-slate-400 hover:text-white disabled:opacity-30 disabled:hover:text-slate-400 transition-colors rounded-lg"
            title="Volgende maand"
            @click="nextMonth && onSelectMonth(nextMonth.monthId)"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>
        <div class="hidden lg:flex items-center gap-1 overflow-x-auto max-w-xl py-0.5">
          <button
            v-for="m in allMonths"
            :key="m.monthId"
            type="button"
            class="text-xs px-2.5 py-1.5 rounded-xl font-medium transition-all"
            :class="
              m.monthId === currentMonth.monthId
                ? 'bg-indigo-600 text-white font-semibold shadow-sm'
                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
            "
            @click="onSelectMonth(m.monthId)"
          >
            {{ m.monthName.slice(0, 3) }}
          </button>
        </div>
      </div>

      <div class="flex items-center gap-2.5 flex-wrap">
        <div class="relative">
          <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Zoek budgetpost..."
            class="bg-slate-800 border border-slate-700 text-xs text-slate-200 pl-8 pr-3 py-1.5 rounded-xl focus:outline-none focus:border-indigo-500 w-36 sm:w-48 placeholder-slate-500"
          />
        </div>
        <div class="hidden sm:flex items-center bg-slate-800/90 rounded-xl p-1 border border-slate-700 text-xs">
          <button
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium transition-all"
            :class="filterType === 'alle' ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-400 hover:text-white'"
            @click="filterType = 'alle'"
          >
            Alle
          </button>
          <button
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium transition-all"
            :class="filterType === 'inkomsten' ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-400 hover:text-white'"
            @click="filterType = 'inkomsten'"
          >
            Inkomsten
          </button>
          <button
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium transition-all"
            :class="filterType === 'uitgaven' ? 'bg-rose-600 text-white font-semibold' : 'text-slate-400 hover:text-white'"
            @click="filterType = 'uitgaven'"
          >
            Uitgaven
          </button>
          <button
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium transition-all"
            :class="filterType === 'sparen' ? 'bg-blue-600 text-white font-semibold' : 'text-slate-400 hover:text-white'"
            @click="filterType = 'sparen'"
          >
            Sparen
          </button>
        </div>
        <button
          type="button"
          class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
          @click="onOpenAddBudgetItem"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Nieuwe Post</span>
        </button>
        <button
          type="button"
          class="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-3 py-1.5 rounded-xl text-xs font-medium border border-slate-700 transition-colors"
          @click="handleExportCSV"
        >
          <Download class="w-3.5 h-3.5" />
          <span class="hidden md:inline">CSV</span>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 items-stretch">
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800/80 hover:border-indigo-500/50 p-3.5 rounded-2xl transition-colors h-full flex flex-col"
        @click="kpiKey = 'income'"
      >
        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
          <span>Totaal Inkomsten</span>
          <span class="text-emerald-400 font-semibold">{{ incomeItems.length }} posten</span>
        </div>
        <div class="text-lg font-bold text-white font-mono">€ {{ euro(totalIncomeBudget) }}</div>
        <div class="mt-1.5 space-y-0.5 text-[11px] font-mono">
          <div class="flex justify-between text-emerald-400">
            <span>Ontvangen</span><span>€ {{ euro(totalIncomeReceived) }}</span>
          </div>
          <div
            class="flex justify-between"
            :class="totalIncomeRemaining > 0 ? 'text-amber-400' : 'text-slate-500'"
          >
            <span>Nog te ontvangen</span><span>€ {{ euro(totalIncomeRemaining) }}</span>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800/80 hover:border-indigo-500/50 p-3.5 rounded-2xl transition-colors h-full flex flex-col"
        @click="kpiKey = 'expense'"
      >
        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
          <span>Totaal Uitgaven</span>
          <span class="text-rose-400 font-semibold">{{ expenseFixedCount }} vast</span>
        </div>
        <div class="text-lg font-bold text-white font-mono">€ {{ euro(totalExpenseFixedBudget) }}</div>
        <div class="mt-1.5 space-y-0.5 text-[11px] font-mono">
          <div class="flex justify-between text-rose-400">
            <span>Betaald</span><span>€ {{ euro(totalExpenseFixedPaid) }}</span>
          </div>
          <div
            class="flex justify-between"
            :class="totalExpenseFixedRemaining > 0 ? 'text-amber-400' : 'text-slate-500'"
          >
            <span>Nog te betalen</span><span>€ {{ euro(totalExpenseFixedRemaining) }}</span>
          </div>
          <div
            v-if="totalExpenseRulesBudget > 0 || totalExpenseOver > 0"
            class="mt-2 pt-1.5 border-t border-slate-800/70 space-y-0.5"
          >
            <div
              v-if="totalExpenseRulesBudget > 0"
              class="flex justify-between text-indigo-300/90"
            >
              <span>Buiten budget</span>
              <span>€ {{ euro(totalExpenseRulesBudget) }}</span>
            </div>
            <div
              v-if="totalExpenseOver > 0"
              class="flex justify-between text-rose-400/90"
            >
              <span>Overschrijding</span><span>€ {{ euro(totalExpenseOver) }}</span>
            </div>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800/80 hover:border-indigo-500/50 p-3.5 rounded-2xl transition-colors h-full flex flex-col"
        @click="kpiKey = 'savings'"
      >
        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
          <span>Totaal Spaargeld</span>
          <span class="text-blue-400 font-semibold">{{ savingsItems.length }} doelen</span>
        </div>
        <div class="text-lg font-bold text-white font-mono">€ {{ euro(totalSavingsBudget) }}</div>
        <div class="mt-1.5 space-y-0.5 text-[11px] font-mono">
          <div class="flex justify-between text-blue-400">
            <span>Gespaard</span><span>€ {{ euro(totalSavingsPaid) }}</span>
          </div>
          <div
            class="flex justify-between"
            :class="totalSavingsRemaining > 0 ? 'text-amber-400' : 'text-slate-500'"
          >
            <span>Nog te sparen</span><span>€ {{ euro(totalSavingsRemaining) }}</span>
          </div>
          <div
            v-if="totalSavingsOver > 0"
            class="mt-2 pt-1.5 border-t border-slate-800/70"
          >
            <div class="flex justify-between text-indigo-300/90">
              <span>Buiten budget gespaard</span><span>€ {{ euro(totalSavingsOver) }}</span>
            </div>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800/80 hover:border-indigo-500/50 p-3.5 rounded-2xl transition-colors h-full flex flex-col"
        @click="kpiKey = 'netto'"
      >
        <div class="flex items-center justify-between text-xs text-slate-400 mb-1">
          <span>Netto Overschot / Saldo</span>
          <span class="text-emerald-400 font-semibold">Begroot</span>
        </div>
        <div
          class="text-lg font-bold font-mono"
          :class="plannedSurplus >= 0 ? 'text-white' : 'text-rose-400'"
          title="Inkomsten − vast begroot − spaargeld (zonder regels)"
        >
          € {{ euro(plannedSurplus) }}
        </div>
        <div class="mt-1.5 space-y-0.5 text-[11px] font-mono">
          <div
            v-if="isCurrentReportingMonth"
            class="flex justify-between text-slate-300"
          >
            <span>Huidig saldo</span><span>€ {{ euro(periodStartBalance) }}</span>
          </div>
          <div
            class="flex justify-between"
            :class="expectedEndOfMonth >= 0 ? 'text-indigo-300' : 'text-rose-400'"
          >
            <span>Verwacht eind</span><span>€ {{ euro(expectedEndOfMonth) }}</span>
          </div>
          <div
            v-if="totalExpenseOutsideBudget > 0"
            class="mt-1 pt-1 border-t border-slate-800/70 space-y-0.5"
          >
            <div
              v-if="totalIncomeOver > 0 || totalIncomeOver > 0"
              class="flex justify-between text-slate-400"
            >
              <span>Inkomsten buiten begroting</span>
              <span class="text-indigo-400">+€ {{ euro(totalIncomeOver) }}</span>
            </div>
            <div
              v-if="totalExpenseRulesBudget > 0"
              class="flex justify-between text-slate-400"
            >
              <span>Buiten budget (regels)</span>
              <span class="text-indigo-300">−€ {{ euro(totalExpenseRulesBudget) }}</span>
            </div>
            <div
              v-if="totalExpenseOver > 0"
              class="flex justify-between text-slate-400"
            >
              <span>Overschrijding</span>
              <span class="text-rose-400">−€ {{ euro(totalExpenseOver) }}</span>
            </div>
          </div>
        </div>
        <p class="mt-auto pt-2 text-[10px] text-slate-500">klik voor detail</p>
      </button>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <template v-for="grp in filteredGroups" :key="grp.groupKey">
        <div
          v-if="!(groupTotals(grp).items.length === 0 && searchQuery)"
          :id="cardId(grp.groupKey)"
          class="bg-[#101726] border border-slate-800/90 rounded-2xl overflow-hidden shadow-md flex flex-col justify-between hover:border-slate-700/80 transition-all"
        >
          <div>
            <div class="p-4 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/40">
              <div class="flex items-center gap-3">
                <div
                  class="w-9 h-9 rounded-xl flex items-center justify-center border"
                  :class="grp.iconBg"
                >
                  <component :is="grp.icon" class="w-4 h-4" :class="grp.iconColor" />
                </div>
                <div>
                  <h3 class="font-bold text-white text-sm tracking-tight">{{ grp.title }}</h3>
                  <p class="text-xs text-slate-400 font-normal">
                    {{ groupTotals(grp).items.length }} budgetten
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2.5">
                <button
                  type="button"
                  class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 hover:underline transition-colors flex items-center gap-1"
                  @click="selectedGroupModal = grp"
                >
                  <span>Openen</span>
                </button>
                <span class="text-xs px-2.5 py-0.5 rounded-full font-medium border" :class="grp.badgeBg">
                  {{ grp.badgeLabel }}
                </span>
              </div>
            </div>

            <div class="overflow-x-auto">
              <table class="w-full text-left text-xs font-mono">
                <thead>
                  <tr
                    class="border-b border-slate-800/80 bg-slate-950/40 text-slate-400 font-sans font-medium text-[11px]"
                  >
                    <th class="py-2.5 px-4">Categorie</th>
                    <th class="py-2.5 px-3 text-center">Betalingen</th>
                    <th class="py-2.5 px-3 text-right">Budget</th>
                    <th class="py-2.5 px-3 text-right">
                      {{
                        grp.type === "inkomsten"
                          ? "Ontvangen"
                          : grp.type === "sparen"
                            ? "Gespaard"
                            : "Betaald"
                      }}
                    </th>
                    <th class="py-2.5 px-4 text-right">
                      {{
                        grp.type === "inkomsten"
                          ? "Nog te ontvangen"
                          : grp.type === "sparen"
                            ? "Nog te sparen"
                            : "Nog te betalen"
                      }}
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-mono">
                  <template v-for="item in groupTotals(grp).items" :key="item.id">
                    <tr v-if="editingId === item.id" class="bg-indigo-950/40">
                      <td class="py-2 px-4 font-sans font-medium text-white">{{ item.name }}</td>
                      <td class="py-2 px-3 text-center text-slate-400">{{ paymentCount(item) }}</td>
                      <td class="py-1 px-2 text-right">
                        <input
                          v-model.number="editActual"
                          type="number"
                          step="0.01"
                          class="w-24 bg-slate-800 text-white text-right px-1.5 py-0.5 rounded border border-indigo-500 focus:outline-none font-mono text-xs"
                          autofocus
                        />
                      </td>
                      <td class="py-2 px-3 text-right text-slate-300">
                        € {{ item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                      </td>
                      <td class="py-2 px-4 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                          <button
                            type="button"
                            class="p-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded transition-colors"
                            title="Opslaan"
                            @click="handleSaveEdit(item.id)"
                          >
                            <Check class="w-3.5 h-3.5" />
                          </button>
                          <button
                            type="button"
                            class="p-1 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded transition-colors"
                            title="Annuleren"
                            @click="editingId = null"
                          >
                            <X class="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                    <tr v-else class="hover:bg-slate-800/40 transition-colors group">
                      <td class="py-2.5 px-4 font-sans font-medium text-slate-200">
                        <div class="flex items-center justify-between gap-2">
                          <div class="min-w-0">
                            <span
                              class="truncate max-w-[180px] hover:text-indigo-300 cursor-pointer block"
                              title="Klik om gekoppelde transacties te bekijken"
                              @click="openItemName(item)"
                            >
                              {{ item.name }}
                            </span>
                            <span
                              v-if="hasPotEnvelope(item)"
                              class="mt-0.5 inline-flex text-[9px] uppercase tracking-wide font-semibold text-amber-300/90 bg-amber-950/40 border border-amber-800/50 px-1.5 py-px rounded"
                            >
                              Potje
                            </span>
                            <ul
                              v-if="item.monthEntries && item.monthEntries.length > 0"
                              class="mt-1 space-y-0.5 text-[10px] text-slate-500 font-normal"
                            >
                              <li
                                v-for="entry in item.monthEntries.slice(0, 3)"
                                :key="entry.id"
                                class="truncate max-w-[200px]"
                              >
                                {{ entry.description || "Zonder omschrijving" }} · €
                                {{ entry.amount.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                              </li>
                              <li v-if="item.monthEntries.length > 3">
                                +{{ item.monthEntries.length - 3 }} meer…
                              </li>
                            </ul>
                          </div>
                          <div
                            class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity shrink-0"
                          >
                            <button
                              v-if="onOpenItemTransactions"
                              type="button"
                              class="p-1 hover:text-indigo-300 text-slate-400 rounded hover:bg-slate-700 transition-colors"
                              title="Transactieoverzicht bekijken"
                              @click="onOpenItemTransactions(item)"
                            >
                              <Eye class="w-3 h-3" />
                            </button>
                            <button
                              type="button"
                              class="p-1 hover:text-white text-slate-400 rounded hover:bg-slate-700 transition-colors"
                              title="Post bewerken (bedrag, verdeling)"
                              @click="
                                onOpenEditBudgetItem ? onOpenEditBudgetItem(item) : handleStartEdit(item)
                              "
                            >
                              <Edit2 class="w-3 h-3" />
                            </button>
                          </div>
                        </div>
                      </td>
                      <td class="py-2.5 px-3 text-center text-slate-400 font-sans">
                        <button
                          v-if="onOpenItemTransactions"
                          type="button"
                          class="px-2 py-0.5 rounded-full text-xs font-mono transition-all"
                          :class="
                            paymentCount(item) > 0
                              ? 'bg-slate-800 text-indigo-300 hover:bg-indigo-900/60 hover:text-white border border-slate-700/80'
                              : 'text-slate-500 hover:text-slate-300'
                          "
                          :title="`Bekijk ${paymentCount(item)} gekoppelde transactie(s) voor ${item.name}`"
                          @click="onOpenItemTransactions(item)"
                        >
                          {{ paymentCount(item) }}
                        </button>
                        <template v-else>{{ paymentCount(item) }}</template>
                      </td>
                      <td class="py-2.5 px-3 text-right text-slate-200">
                        € {{ item.actual.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                      </td>
                      <td class="py-2.5 px-3 text-right text-slate-200">
                        <div class="flex flex-col items-end gap-0.5">
                          <button
                            v-if="onOpenItemTransactions && hasBudget(item)"
                            type="button"
                            class="hover:text-indigo-300 hover:underline font-mono transition-colors"
                            title="Klik om gekoppelde bankmutaties te inspecteren"
                            @click="onOpenItemTransactions(item)"
                          >
                            €
                            {{
                              item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                            }}
                          </button>
                          <span
                            v-else-if="!hasBudget(item)"
                            class="text-slate-500"
                            title="Geen begroting deze maand — telt niet mee in totaal"
                          >
                            —
                          </span>
                          <span v-else>
                            €
                            {{
                              item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                            }}
                          </span>
                          <span
                            v-if="hasPotEnvelope(item) && (item.shadowSpent ?? 0) > 0"
                            class="text-[10px] font-sans font-medium"
                            :class="shadowOverspend(item) > 0 ? 'text-rose-400' : 'text-amber-400/90'"
                            :title="
                              shadowOverspend(item) > 0
                                ? 'Schaduwuitgaven vanuit het potje, inclusief overschrijding'
                                : 'Schaduwuitgaven vanuit het potje (tellen niet mee in de begroting)'
                            "
                          >
                            schaduw €
                            {{
                              item.shadowSpent!.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                            }}
                            <template v-if="shadowOverspend(item) > 0">
                              · +€
                              {{
                                shadowOverspend(item).toLocaleString("nl-NL", {
                                  minimumFractionDigits: 2,
                                })
                              }}
                            </template>
                          </span>
                        </div>
                      </td>
                      <td class="py-2.5 px-4 text-right font-medium">
                        <template v-if="grp.type === 'inkomsten'">
                          <span
                            v-if="hasBudget(item) && item.paidOrReceived > item.actual"
                            class="text-emerald-400 font-bold"
                          >
                            +€
                            {{
                              (item.paidOrReceived - item.actual).toLocaleString("nl-NL", {
                                minimumFractionDigits: 2,
                              })
                            }}
                          </span>
                          <span
                            v-else-if="hasBudget(item) && item.paidOrReceived < item.actual"
                            class="text-amber-400 font-bold"
                          >
                            €
                            {{
                              (item.actual - item.paidOrReceived).toLocaleString("nl-NL", {
                                minimumFractionDigits: 2,
                              })
                            }}
                          </span>
                          <span v-else-if="!hasBudget(item) && item.paidOrReceived > 0" class="text-indigo-400">
                            +€
                            {{
                              item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                            }}
                          </span>
                          <span v-else class="text-slate-400">€ 0,00</span>
                        </template>
                        <template v-else>
                          <span
                            v-if="hasBudget(item) && item.paidOrReceived > item.actual"
                            class="text-rose-400 font-bold"
                          >
                            € -
                            {{
                              (item.paidOrReceived - item.actual).toLocaleString("nl-NL", {
                                minimumFractionDigits: 2,
                              })
                            }}
                          </span>
                          <span
                            v-else-if="hasBudget(item) && item.paidOrReceived < item.actual && item.paidOrReceived > 0"
                            class="text-amber-400"
                          >
                            €
                            {{
                              (item.actual - item.paidOrReceived).toLocaleString("nl-NL", {
                                minimumFractionDigits: 2,
                              })
                            }}
                          </span>
                          <span
                            v-else-if="hasBudget(item) && item.paidOrReceived < item.actual && item.paidOrReceived === 0"
                            class="text-slate-300"
                          >
                            € {{ item.actual.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                          </span>
                          <span v-else-if="!hasBudget(item) && item.paidOrReceived > 0" class="text-rose-400">
                            € -
                            {{
                              item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                            }}
                          </span>
                          <span v-else class="text-slate-400">€ 0,00</span>
                        </template>
                      </td>
                    </tr>
                  </template>
                </tbody>
                <tfoot>
                  <tr class="border-t border-slate-700/80 bg-slate-900/60 font-bold text-white text-xs">
                    <td class="py-3 px-4 font-sans">Totaal</td>
                    <td class="py-3 px-3 text-center text-slate-400 font-sans">
                      {{
                        groupTotals(grp).totalPaymentCount > 0
                          ? groupTotals(grp).totalPaymentCount
                          : ""
                      }}
                    </td>
                    <td class="py-3 px-3 text-right">
                      €
                      {{
                        groupTotals(grp).totalBudget.toLocaleString("nl-NL", {
                          minimumFractionDigits: 2,
                        })
                      }}
                    </td>
                    <td class="py-3 px-3 text-right">
                      €
                      {{
                        groupTotals(grp).totalPaidOrReceived.toLocaleString("nl-NL", {
                          minimumFractionDigits: 2,
                        })
                      }}
                    </td>
                    <td class="py-3 px-4 text-right">
                      <template v-if="grp.type === 'inkomsten'">
                        <span v-if="groupTotals(grp).incomeRemaining > 0" class="text-amber-400">
                          €
                          {{
                            groupTotals(grp).incomeRemaining.toLocaleString("nl-NL", {
                              minimumFractionDigits: 2,
                            })
                          }}
                        </span>
                        <span v-else>€ 0,00</span>
                      </template>
                      <template v-else>
                        <span v-if="groupTotals(grp).expenseRemaining > 0">
                          €
                          {{
                            groupTotals(grp).expenseRemaining.toLocaleString("nl-NL", {
                              minimumFractionDigits: 2,
                            })
                          }}
                        </span>
                        <span v-else>€ 0,00</span>
                      </template>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>

          <template v-if="grp.type === 'inkomsten'">
            <div
              v-if="groupTotals(grp).incomeSurplus > 0"
              class="bg-emerald-600 text-white font-bold px-4 py-2.5 flex items-center justify-between text-xs tracking-wide"
            >
              <span>Teveel ontvangen</span>
              <span class="font-mono text-sm font-extrabold">
                +€
                {{
                  groupTotals(grp).incomeSurplus.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                }}
              </span>
            </div>
            <div
              v-else-if="groupTotals(grp).incomeRemaining > 0"
              class="bg-amber-950/60 border-t border-amber-800/40 text-amber-300 font-medium px-4 py-2.5 flex items-center justify-between text-xs tracking-wide"
            >
              <span>Nog te ontvangen deze maand</span>
              <span class="font-mono text-sm font-bold text-amber-400">
                €
                {{
                  groupTotals(grp).incomeRemaining.toLocaleString("nl-NL", {
                    minimumFractionDigits: 2,
                  })
                }}
              </span>
            </div>
            <div
              v-else
              class="bg-slate-800/60 border-t border-slate-700/60 text-slate-300 font-medium px-4 py-2.5 flex items-center justify-between text-xs tracking-wide"
            >
              <span>Inkomsten volledig ontvangen</span>
              <span class="text-emerald-400 font-bold font-mono">✓ Voldaan</span>
            </div>
          </template>
          <template v-else>
            <div
              v-if="groupTotals(grp).expenseOverpaid > 0"
              class="bg-red-600 text-white font-bold px-4 py-2.5 flex items-center justify-between text-xs tracking-wide"
            >
              <span>Teveel betaald</span>
              <span class="font-mono text-sm font-extrabold">
                € -
                {{
                  groupTotals(grp).expenseOverpaid.toLocaleString("nl-NL", {
                    minimumFractionDigits: 2,
                  })
                }}
              </span>
            </div>
            <div
              v-else-if="groupTotals(grp).expenseRemaining > 0"
              class="bg-slate-800/50 border-t border-slate-700/50 text-slate-300 font-medium px-4 py-2.5 flex items-center justify-between text-xs tracking-wide"
            >
              <span>{{
                grp.type === "sparen" ? "Binnen budget (Nog te sparen)" : "Binnen budget (Nog te betalen)"
              }}</span>
              <span class="font-mono text-sm font-bold text-slate-200">
                €
                {{
                  groupTotals(grp).expenseRemaining.toLocaleString("nl-NL", {
                    minimumFractionDigits: 2,
                  })
                }}
              </span>
            </div>
            <div
              v-else
              class="bg-emerald-950/40 border-t border-emerald-800/40 text-emerald-300 font-medium px-4 py-2.5 flex items-center justify-between text-xs tracking-wide"
            >
              <span>Alles voldaan binnen budget</span>
              <span class="text-emerald-400 font-bold font-mono">
                {{ grp.type === "sparen" ? "✓ 100% Gespaard" : "✓ 100% Betaald" }}
              </span>
            </div>
          </template>
        </div>
      </template>
    </div>

    <div
      v-if="selectedGroupModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in"
    >
      <div
        class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden"
      >
        <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-850">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-xl flex items-center justify-center border"
              :class="selectedGroupModal.iconBg"
            >
              <component
                :is="selectedGroupModal.icon"
                class="w-5 h-5"
                :class="selectedGroupModal.iconColor"
              />
            </div>
            <div>
              <h3 class="font-bold text-white text-base">{{ selectedGroupModal.title }}</h3>
              <p class="text-xs text-slate-400">
                Begrotingsposten en gekoppelde bankmutaties voor {{ currentMonth.monthName }}
                {{ currentMonth.year }}
              </p>
            </div>
          </div>
          <button
            type="button"
            class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
            @click="selectedGroupModal = null"
          >
            <X class="w-5 h-5" />
          </button>
        </div>
        <div class="p-5 space-y-6 overflow-y-auto">
          <div>
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
              Begrotingsposten
            </h4>
            <div class="space-y-2">
              <div
                v-for="item in currentMonth.items.filter((i) => i.group === selectedGroupModal!.groupKey)"
                :key="item.id"
                class="bg-slate-800/60 border border-slate-700/60 p-3 rounded-xl flex items-center justify-between"
              >
                <div>
                  <p class="font-semibold text-white text-sm">{{ item.name }}</p>
                  <p class="text-xs text-slate-400">
                    {{ item.paymentCount || 0 }} gekoppelde transacties
                    <template v-if="hasPotEnvelope(item) && (item.shadowSpent ?? 0) > 0">
                      · schaduw €
                      {{ item.shadowSpent!.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                    </template>
                  </p>
                </div>
                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <p class="text-xs text-slate-400">Budget</p>
                    <p class="font-bold text-white font-mono text-sm">
                      € {{ item.actual.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                    </p>
                  </div>
                  <div class="text-right pl-3 border-l border-slate-700">
                    <p class="text-xs text-slate-400">
                      {{ item.type === "inkomsten" ? "Ontvangen" : "Betaald" }}
                    </p>
                    <p
                      class="font-bold font-mono text-sm"
                      :class="
                        item.paidOrReceived >= item.actual && item.actual > 0
                          ? 'text-emerald-400'
                          : 'text-amber-400'
                      "
                    >
                      €
                      {{
                        item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                      }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div>
            <h4
              class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3 flex items-center justify-between"
            >
              <span>Banktransacties ({{ selectedGroupModal.title }})</span>
              <span class="text-[11px] font-normal text-indigo-400">Live vanuit bank</span>
            </h4>
            <div
              v-if="
                monthTransactions.filter((t) => t.categoryGroup === selectedGroupModal!.groupKey)
                  .length === 0
              "
              class="p-4 bg-slate-950/40 rounded-xl border border-slate-800 text-center text-xs text-slate-500"
            >
              Nog geen banktransacties geregistreerd voor deze categorie in {{ currentMonth.monthName }}.
            </div>
            <div v-else class="space-y-1.5">
              <div
                v-for="tx in monthTransactions.filter(
                  (t) => t.categoryGroup === selectedGroupModal!.groupKey
                )"
                :key="tx.id"
                class="bg-slate-950/60 border border-slate-800/80 p-2.5 rounded-xl flex items-center justify-between text-xs font-mono"
              >
                <div class="flex items-center gap-2.5 overflow-hidden">
                  <TransactionDate :date="tx.date" :time="tx.time" size="sm" />
                  <span class="font-sans font-medium text-slate-200 truncate max-w-[260px]">
                    {{ tx.counterparty || tx.description }}
                  </span>
                </div>
                <span
                  class="font-bold"
                  :class="tx.amount > 0 ? 'text-emerald-400' : 'text-rose-400'"
                >
                  {{ tx.amount > 0 ? "+" : "" }}€
                  {{ Math.abs(tx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
            </div>
          </div>
        </div>
        <div class="p-4 border-t border-slate-800 bg-slate-850 flex items-center justify-between">
          <button
            type="button"
            class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 flex items-center gap-1.5"
            @click="
              selectedGroupModal = null;
              onOpenAddBudgetItem();
            "
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Nieuwe post in deze groep toevoegen</span>
          </button>
          <button
            type="button"
            class="bg-slate-800 hover:bg-slate-700 text-white px-4 py-1.5 rounded-xl text-xs font-medium transition-colors"
            @click="selectedGroupModal = null"
          >
            Sluiten
          </button>
        </div>
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
