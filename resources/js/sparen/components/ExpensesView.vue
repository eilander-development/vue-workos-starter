<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
  ArrowDownCircle,
  Search,
  CheckCircle2,
  Clock,
  Plus,
  Edit2,
  HelpCircle,
  TrendingDown,
  Eye,
  PiggyBank,
} from "lucide-vue-next";
import type { MonthlyBudget, BudgetItem, BudgetCategoryGroup, Transaction, CategoryDefinition, SavingsGoal } from "../types";
import KpiBreakdownModal from "./KpiBreakdownModal.vue";
import { budgetItemRows, sumBudgetedAmount, sumBudgetedPaid, sumBudgetedRemaining, sumBudgetedOver } from "../kpiBreakdown";
import {
  computePotSettlement,
  hasPotEnvelope,
  potCompensationStatus,
  potGoalsLinkedToItem,
  shadowOverspend,
  type PotSettlement,
} from "../potSettlement";
import PotSettlementModal from "./PotSettlementModal.vue";

const props = withDefaults(
  defineProps<{
    currentMonth: MonthlyBudget;
    allMonths?: MonthlyBudget[];
    transactions?: Transaction[];
    onUpdateBudgetItem: (itemId: string, updates: Partial<BudgetItem>) => void;
    onOpenAddBudgetItem: () => void;
    onOpenEditBudgetItem?: (item: BudgetItem) => void;
    onOpenItemTransactions?: (item: BudgetItem) => void;
    categories?: CategoryDefinition[];
    savingsGoals?: SavingsGoal[];
  }>(),
  {
    transactions: () => [],
    categories: () => [],
    savingsGoals: () => [],
  }
);

const searchTerm = ref("");
const selectedGroup = ref("ALL");
const showExplanation = ref(false);
const kpiKey = ref<"budget" | "paid" | "remaining" | "over" | null>(null);

const expenseItems = computed(() => props.currentMonth.items.filter((i) => i.type === "uitgaven"));
const filteredItems = computed(() =>
  expenseItems.value.filter((item) => {
    const matchesSearch = item.name.toLowerCase().includes(searchTerm.value.toLowerCase());
    const matchesGroup = selectedGroup.value === "ALL" || item.group === selectedGroup.value;
    return matchesSearch && matchesGroup;
  })
);

const totalExpenseBudget = computed(() => sumBudgetedAmount(expenseItems.value));
const totalExpensePaid = computed(() => sumBudgetedPaid(expenseItems.value));
const totalExpenseRemaining = computed(() => sumBudgetedRemaining(expenseItems.value));
const totalExpenseOverpaid = computed(() => sumBudgetedOver(expenseItems.value));

const expenseGroups = computed((): BudgetCategoryGroup[] => {
  const present = new Set(expenseItems.value.map((i) => i.group));
  if (present.size === 0) return [];

  if (props.categories.length > 0) {
    const ordered = props.categories
      .filter((c) => c.type === "uitgaven" && present.has(c.name))
      .map((c) => c.name);
    const extras = [...present].filter((g) => !ordered.includes(g)).sort();
    return [...ordered, ...extras];
  }

  return [...present].sort();
});

function groupItemCount(group: BudgetCategoryGroup): number {
  return expenseItems.value.filter((i) => i.group === group).length;
}

watch(
  () => [expenseGroups.value, props.currentMonth.monthId] as const,
  () => {
    if (selectedGroup.value !== "ALL" && !expenseGroups.value.includes(selectedGroup.value)) {
      selectedGroup.value = "ALL";
    }
  }
);

const kpiMeta = computed(
  () =>
    ({
      budget: {
        title: `Totaal begroot — ${props.currentMonth.monthName}`,
        formula: "Som van alle uitgavenposten: kolom Begroot.",
        color: "text-white",
      },
      paid: {
        title: `Reeds afgeschreven — ${props.currentMonth.monthName}`,
        formula: "Som van bankbedragen op uitgavenposten met begroting > 0.",
        color: "text-rose-400",
      },
      remaining: {
        title: `Nog te betalen — ${props.currentMonth.monthName}`,
        formula: "Per post: max(0, begroot − bank). Alleen posten die nog open staan.",
        color: "text-amber-400",
      },
      over: {
        title: `Budgetoverschrijdingen — ${props.currentMonth.monthName}`,
        formula: "Per post: max(0, bank − begroot). Alleen posten boven budget.",
        color: "text-rose-500",
      },
    }) as const
);

const kpiBreakdown = computed(() =>
  kpiKey.value
    ? budgetItemRows(expenseItems.value, kpiKey.value, (item) => {
        kpiKey.value = null;
        props.onOpenItemTransactions?.(item);
      })
    : null
);

function itemState(item: BudgetItem) {
  const isZeroBudget = item.actual === 0;
  const isZeroPaid = item.paidOrReceived === 0;
  const isZeroNotApplicable = isZeroBudget && isZeroPaid;
  const isPaid = !isZeroBudget && item.paidOrReceived >= item.actual;
  const isPartiallyPaid = !isZeroBudget && item.paidOrReceived > 0 && item.paidOrReceived < item.actual;
  const isOverBudget = !isZeroBudget && item.paidOrReceived > item.actual;
  const pending = Math.max(0, item.actual - item.paidOrReceived);
  const percent =
    item.actual > 0 ? Math.min(100, Math.round((item.paidOrReceived / item.actual) * 100)) : 0;
  return { isZeroNotApplicable, isPaid, isPartiallyPaid, isOverBudget, pending, percent };
}

function paidAmountClass(item: BudgetItem): string {
  if (item.paidOrReceived <= 0) return "text-slate-400";
  if (itemState(item).isOverBudget) return "text-rose-400";
  return "text-emerald-400";
}

function pinAmountClass(item: BudgetItem): string {
  return shadowOverspend(item) > 0 ? "text-rose-400" : "text-emerald-400";
}

function overspendAmount(item: BudgetItem): number {
  if (hasPotEnvelope(item)) {
    return shadowOverspend(item);
  }
  return itemState(item).isOverBudget ? item.paidOrReceived - item.actual : 0;
}

function linkedPotGoal(item: BudgetItem): SavingsGoal | undefined {
  return potGoalsLinkedToItem(item.id, props.savingsGoals)[0];
}

function potSettlementFor(item: BudgetItem): PotSettlement | null {
  const goal = linkedPotGoal(item);
  if (!goal) return null;
  return computePotSettlement(goal, props.currentMonth, props.transactions);
}

const potDetailItem = ref<BudgetItem | null>(null);

const potDetailGoal = computed(() =>
  potDetailItem.value ? linkedPotGoal(potDetailItem.value) ?? null : null
);

const potDetailSettlement = computed((): PotSettlement | null => {
  if (!potDetailItem.value) return null;
  return potSettlementFor(potDetailItem.value);
});

function openPotDetail(item: BudgetItem) {
  potDetailItem.value = item;
}

function closePotDetail() {
  potDetailItem.value = null;
}
</script>

<template>
  <div id="expenses-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <ArrowDownCircle class="w-5 h-5 text-rose-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">
            Uitgaven & Vaste Lasten • {{ currentMonth.monthName }} {{ currentMonth.year }}
          </h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Gedetailleerd beheer van alle woonlasten, abonnementen, leningen en dagelijkse uitgaven met
          automatische incassoherkenning
        </p>
      </div>
      <div class="flex items-center gap-2.5">
        <button
          type="button"
          class="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-xl text-xs font-medium border border-slate-700 transition-colors"
          title="Uitleg over Budget vs Betaald"
          @click="showExplanation = !showExplanation"
        >
          <HelpCircle class="w-4 h-4 text-indigo-400" />
          <span>Waarom Budget vs Bank?</span>
        </button>
        <button
          type="button"
          class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition-all active:scale-95"
          @click="onOpenAddBudgetItem"
        >
          <Plus class="w-4 h-4" />
          <span>Nieuwe Uitgave Post</span>
        </button>
      </div>
    </div>

    <div
      v-if="showExplanation"
      class="bg-indigo-950/40 border border-indigo-500/30 p-4 rounded-2xl text-xs text-slate-300 space-y-2 animate-fade-in"
    >
      <h4 class="font-bold text-white flex items-center gap-1.5">
        <TrendingDown class="w-4 h-4 text-rose-400" />
        <span>Het verschil tussen "Budget" en "Betaald via rekening":</span>
      </h4>
      <ul class="list-disc list-inside space-y-1 text-slate-300 pl-1">
        <li>
          <strong class="text-white">Budget:</strong> Het bedrag dat je voor deze post in deze maand hebt
          gereserveerd (bijv. € 500 voor Boodschappen of € 150 voor Verwarming).
        </li>
        <li>
          <strong class="text-white">Betaald via rekening:</strong> De som van alle echte bankafschrijvingen
          van deze maand die aan deze post zijn gekoppeld.
        </li>
        <li>
          <strong class="text-white">Inzicht:</strong> Zo zie je meteen of een automatische incasso al is
          afgeschreven, of je nog budget over hebt, of dat je over budget gaat.
        </li>
      </ul>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        title="Klik om de posten in dit totaal te zien"
        @click="kpiKey = 'budget'"
      >
        <span class="text-xs text-slate-400 block font-medium">
          Totaal Begroot ({{ currentMonth.monthName }}):
        </span>
        <div class="text-xl font-bold text-white font-mono mt-1">
          € {{ totalExpenseBudget.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-slate-500 mt-0.5 block">
          {{ expenseItems.length }} begrotingsposten · klik voor detail
        </span>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        @click="kpiKey = 'paid'"
      >
        <span class="text-xs text-slate-400 block font-medium">Reeds Afgeschreven (Bank):</span>
        <div class="text-xl font-bold text-rose-400 font-mono mt-1">
          € {{ totalExpensePaid.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-rose-400/80 mt-0.5 block">
          {{
            totalExpenseBudget > 0
              ? `${Math.round((totalExpensePaid / totalExpenseBudget) * 100)}% voldaan`
              : "0%"
          }}
          · klik voor detail
        </span>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        @click="kpiKey = 'remaining'"
      >
        <span class="text-xs text-slate-400 block font-medium">Nog te Betalen (Binnen budget):</span>
        <div class="text-xl font-bold text-amber-400 font-mono mt-1">
          € {{ totalExpenseRemaining.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-slate-500 mt-0.5 block">Verwachte incasso's · klik voor detail</span>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        @click="kpiKey = 'over'"
      >
        <span class="text-xs text-slate-400 block font-medium">Budgetoverschrijdingen:</span>
        <div class="text-xl font-bold text-rose-500 font-mono mt-1">
          € {{ totalExpenseOverpaid.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-slate-500 mt-0.5 block">
          Boven begroting betaald · klik voor detail
        </span>
      </button>
    </div>

    <div class="bg-[#101726] border border-slate-800 p-4 rounded-2xl shadow-sm">
      <div class="flex items-center gap-2 overflow-x-auto">
        <div class="relative flex-1 min-w-[12rem]">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Zoek in uitgavenposten (bv. Hypotheek, GreenChoice, Benzine)..."
            class="w-full bg-slate-800/80 border border-slate-700 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
          />
        </div>
        <div class="flex items-center bg-slate-800/90 rounded-xl p-1 border border-slate-700 text-xs shrink-0">
          <button
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition-all"
            :class="
              selectedGroup === 'ALL'
                ? 'bg-indigo-600 text-white font-semibold'
                : 'text-slate-400 hover:text-white'
            "
            @click="selectedGroup = 'ALL'"
          >
            Alle ({{ expenseItems.length }})
          </button>
          <button
            v-for="grp in expenseGroups"
            :key="grp"
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium whitespace-nowrap transition-all"
            :class="
              selectedGroup === grp
                ? 'bg-indigo-600 text-white font-semibold'
                : 'text-slate-400 hover:text-white'
            "
            @click="selectedGroup = grp"
          >
            {{ grp }} ({{ groupItemCount(grp) }})
          </button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="item in filteredItems"
        :key="item.id"
        class="bg-[#101726] border border-slate-800/90 hover:border-slate-700/80 p-5 rounded-2xl shadow-sm transition-all flex flex-col justify-between"
      >
        <div>
          <div class="flex items-start justify-between mb-4">
            <div>
              <h4 class="font-bold text-white text-base">{{ item.name }}</h4>
              <div class="mt-1 flex items-center gap-1.5">
                <span class="text-xs text-slate-400">{{ item.group }}</span>
                <button
                  v-if="hasPotEnvelope(item)"
                  type="button"
                  class="inline-flex text-[9px] uppercase tracking-wide font-semibold text-amber-300/90 bg-amber-950/40 border border-amber-800/50 px-1.5 py-px rounded hover:bg-amber-900/60 hover:text-amber-100 transition-colors"
                  title="Bekijk potje"
                  @click="openPotDetail(item)"
                >
                  Potje
                </button>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span
                v-if="itemState(item).isZeroNotApplicable"
                class="text-[11px] font-medium px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 border border-slate-700/80"
              >
                Niet van toepassing (€0)
              </span>
              <span
                v-else-if="itemState(item).isOverBudget"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-rose-950/80 text-rose-400 border border-rose-800/80"
              >
                Teveel betaald
              </span>
              <span
                v-else-if="itemState(item).isPaid"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-950/80 text-emerald-400 border border-emerald-800/80 flex items-center gap-1.5"
              >
                <CheckCircle2 class="w-3.5 h-3.5" />
                <span>Voldaan via Bank</span>
              </span>
              <span
                v-else-if="itemState(item).isPartiallyPaid"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-950/80 text-amber-400 border border-amber-800/80 flex items-center gap-1.5"
              >
                <Clock class="w-3.5 h-3.5" />
                <span>Deels voldaan</span>
              </span>
              <span
                v-else
                class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 border border-slate-700/80 flex items-center gap-1.5"
              >
                <Clock class="w-3.5 h-3.5" />
                <span>Openstaand</span>
              </span>
              <button
                v-if="onOpenEditBudgetItem"
                type="button"
                class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
                title="Budget en frequentie aanpassen"
                @click="onOpenEditBudgetItem(item)"
              >
                <Edit2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div
            v-if="!itemState(item).isZeroNotApplicable"
            class="w-full bg-slate-800 h-1.5 rounded-full overflow-hidden mb-3"
          >
            <div
              class="h-full rounded-full transition-all"
              :class="
                itemState(item).isOverBudget
                  ? 'bg-rose-500'
                  : itemState(item).isPaid
                    ? 'bg-emerald-400'
                    : itemState(item).isPartiallyPaid
                      ? 'bg-amber-400'
                      : 'bg-slate-600'
              "
              :style="{ width: `${itemState(item).percent}%` }"
            />
          </div>

          <div class="rounded-xl overflow-hidden border border-slate-800/80">
          <div class="bg-slate-950/50 p-3.5 space-y-2.5 font-mono text-xs">
            <div class="flex justify-between items-center text-slate-300">
              <span class="font-sans font-medium text-slate-400">
                Begroot voor {{ currentMonth.monthName }}:
              </span>
              <span class="font-bold text-white text-sm">
                € {{ item.actual.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
            <div class="flex justify-between items-center text-slate-300 pt-1.5 border-t border-slate-800">
              <span class="font-sans font-medium text-slate-400">
                {{ hasPotEnvelope(item) ? "Betaald (potje):" : "Betaald via rekening (Live bank):" }}
              </span>
              <span class="font-bold text-sm" :class="paidAmountClass(item)">
                € {{ item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
            <div
              v-if="hasPotEnvelope(item) && (item.shadowSpent ?? 0) > 0"
              class="flex justify-between items-center pt-1.5 border-t border-slate-800"
              :class="pinAmountClass(item)"
            >
              <span class="font-sans font-medium">Uitgegeven:</span>
              <span class="font-bold text-sm">
                €
                {{
                  item.shadowSpent!.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                }}
              </span>
            </div>
            <div
              v-if="itemState(item).pending > 0"
              class="flex justify-between items-center text-amber-400 pt-1.5 border-t border-slate-800"
            >
              <span class="font-sans font-medium">Nog te betalen:</span>
              <span class="font-bold">
                €
                {{
                  itemState(item).pending.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                }}
              </span>
            </div>
          </div>
          <div
            v-if="overspendAmount(item) > 0"
            class="bg-red-600 text-white font-bold px-3.5 py-2 flex items-center justify-between text-xs tracking-wide"
          >
            <span>Overschrijding</span>
            <span class="font-mono">
              € −
              {{
                overspendAmount(item).toLocaleString("nl-NL", { minimumFractionDigits: 2 })
              }}
            </span>
          </div>
          </div>

          <div
            v-if="hasPotEnvelope(item) && potSettlementFor(item)"
            class="mt-3 bg-amber-950/30 border border-amber-800/50 rounded-xl px-3 py-2.5 space-y-1.5 text-[11px] font-mono"
          >
            <button
              type="button"
              class="w-full flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 border text-left"
              :class="
                potCompensationStatus(potSettlementFor(item)!).sufficient
                  ? 'bg-emerald-950/40 border-emerald-800/60 text-emerald-300'
                  : 'bg-rose-950/30 border-rose-800/50 text-rose-300'
              "
              @click="openPotDetail(item)"
            >
              <span class="font-sans font-semibold text-[10px] uppercase tracking-wide">
                {{
                  potCompensationStatus(potSettlementFor(item)!).sufficient
                    ? "Voldoende gecompenseerd"
                    : "Nog niet voldoende"
                }}
              </span>
              <PiggyBank class="w-3.5 h-3.5 shrink-0 opacity-80" />
            </button>
            <div class="flex justify-between text-slate-300">
              <span>Gecompenseerd</span>
              <span class="text-emerald-400">
                €
                {{
                  potSettlementFor(item)!.compensated.toLocaleString("nl-NL", {
                    minimumFractionDigits: 2,
                  })
                }}
              </span>
            </div>
            <div
              v-if="!potCompensationStatus(potSettlementFor(item)!).sufficient"
              class="flex justify-between font-bold text-amber-400"
            >
              <span>Nog te compenseren</span>
              <span>
                €
                {{
                  potCompensationStatus(potSettlementFor(item)!).shortfall.toLocaleString("nl-NL", {
                    minimumFractionDigits: 2,
                  })
                }}
              </span>
            </div>
            <button
              type="button"
              class="w-full text-left text-indigo-400 hover:text-indigo-300 font-sans font-semibold pt-1"
              @click="openPotDetail(item)"
            >
              Bekijk potje →
            </button>
          </div>
        </div>

        <div class="mt-3 pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-xs">
          <span class="text-slate-500 text-[11px] font-mono">
            {{ item.paymentCount || (item.paidOrReceived > 0 ? 1 : 0) }} afschrijving(en)
          </span>
          <button
            v-if="onOpenItemTransactions"
            type="button"
            class="text-indigo-400 hover:text-indigo-300 font-semibold text-xs flex items-center gap-1.5 hover:underline py-0.5 px-1 rounded transition-colors"
            :title="`Bekijk alle gekoppelde bankmutaties voor ${item.name}`"
            @click="onOpenItemTransactions(item)"
          >
            <Eye class="w-3.5 h-3.5" />
            <span>Bekijk transacties</span>
          </button>
          <button
            v-else-if="onOpenEditBudgetItem"
            type="button"
            class="text-indigo-400 hover:text-indigo-300 font-medium text-xs hover:underline"
            @click="onOpenEditBudgetItem(item)"
          >
            Aanpassen
          </button>
        </div>
      </div>
    </div>

    <KpiBreakdownModal
      :is-open="Boolean(kpiKey && kpiBreakdown)"
      :title="kpiKey ? kpiMeta[kpiKey].title : ''"
      :formula="kpiKey ? kpiMeta[kpiKey].formula : ''"
      subtitle="Klik op een rij om de gekoppelde bankmutaties te openen."
      :columns="kpiBreakdown?.columns ?? []"
      :rows="kpiBreakdown?.rows ?? []"
      :total-value="kpiBreakdown?.total ?? 0"
      :total-color-class="kpiKey ? kpiMeta[kpiKey].color : 'text-white'"
      :on-close="() => (kpiKey = null)"
    />

    <PotSettlementModal
      :is-open="!!potDetailGoal && !!potDetailSettlement"
      :on-close="closePotDetail"
      :goal="potDetailGoal"
      :settlement="potDetailSettlement"
      :current-month="currentMonth"
    />
  </div>
</template>
