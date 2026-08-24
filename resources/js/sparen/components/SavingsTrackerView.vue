<script setup lang="ts">
import { computed, ref, type Component } from "vue";
import {
  PiggyBank,
  ShieldCheck,
  Plus,
  Heart,
  Car,
  Home,
  Palmtree,
  Cat,
  Edit2,
  Trash2,
  CheckCircle2,
  Info,
  ChevronDown,
  ChevronUp,
} from "lucide-vue-next";
import type {
  SavingsRow,
  BudgetItem,
  SavingsGoal,
  Transaction,
  MonthlyBudget,
} from "../types";
import {
  transactionMatchesSavingsGoal,
  transactionMatchesSavingsGoalDeposit,
  isSavingsWithdrawalTransaction,
  savingsBalanceDelta,
} from "../matchSavings";
import { computePotSettlement, isPotGoal, potCompensationStatus, type PotSettlement } from "../potSettlement";
import TransactionDate from "./TransactionDate.vue";
import PotSettlementModal from "./PotSettlementModal.vue";

const props = defineProps<{
  savingsHistory: SavingsRow[];
  savingsItems: BudgetItem[];
  savingsGoals: SavingsGoal[];
  transactions: Transaction[];
  currentMonth?: MonthlyBudget;
  onOpenAddGoal: () => void;
  onEditGoal: (goal: SavingsGoal) => void;
  onDeleteGoal: (goalId: string) => void;
  onUpdateSavingsRow: (monthId: string, updates: Partial<SavingsRow>) => void;
}>();

const expandedGoalId = ref<string | null>(null);
const potDetailGoalId = ref<string | null>(null);

const potDetailGoal = computed(() =>
  props.savingsGoals.find((goal) => goal.id === potDetailGoalId.value) ?? null
);

const potDetailSettlement = computed((): PotSettlement | null => {
  if (!potDetailGoal.value || !props.currentMonth) return null;
  return computePotSettlement(potDetailGoal.value, props.currentMonth, props.transactions);
});

function openPotDetail(goal: SavingsGoal) {
  potDetailGoalId.value = goal.id;
}

function closePotDetail() {
  potDetailGoalId.value = null;
}

function getGoalIcon(iconName: string): Component {
  switch (iconName) {
    case "ShieldCheck":
      return ShieldCheck;
    case "Home":
      return Home;
    case "Palmtree":
      return Palmtree;
    case "Cat":
      return Cat;
    case "Car":
      return Car;
    case "Heart":
      return Heart;
    default:
      return PiggyBank;
  }
}

const goalsWithCalculations = computed(() =>
  props.savingsGoals.map((goal) => {
    if (isPotGoal(goal)) {
      return {
        ...goal,
        isPot: true as const,
      };
    }

    const matchingTxs = props.transactions.filter((tx) => {
      if (tx.type !== "Sparen" && tx.categoryGroup !== "Spaargeld") return false;
      return transactionMatchesSavingsGoal(tx, goal);
    });

    const totalFromTxs = matchingTxs.reduce((sum, tx) => sum + savingsBalanceDelta(tx), 0);
    const currentBalance = goal.initialAmount + totalFromTxs;
    const progressPercent =
      goal.targetAmount > 0
        ? Math.min(100, Math.round((currentBalance / goal.targetAmount) * 100))
        : 0;

    const depositTxs = matchingTxs.filter((tx) =>
      transactionMatchesSavingsGoalDeposit(tx, goal)
    );
    const withdrawalTxs = matchingTxs.filter((tx) =>
      isSavingsWithdrawalTransaction(tx)
    );

    const totalDeposits = depositTxs.reduce((sum, tx) => sum + savingsBalanceDelta(tx), 0);
    const totalWithdrawals = withdrawalTxs.reduce(
      (sum, tx) => sum + Math.abs(savingsBalanceDelta(tx)),
      0
    );

    return {
      ...goal,
      isPot: false as const,
      currentBalance,
      progressPercent,
      transactions: depositTxs,
      totalDeposits,
      totalWithdrawals,
      withdrawalTxs,
    };
  })
);

const totalCalculatedSavings = computed(() =>
  goalsWithCalculations.value
    .filter((g) => !g.isPot)
    .reduce((sum, g) => sum + g.currentBalance, 0)
);
const totalTargetSavings = computed(() =>
  goalsWithCalculations.value
    .filter((g) => !g.isPot)
    .reduce((sum, g) => sum + g.targetAmount, 0)
);
const overallProgress = computed(() =>
  totalTargetSavings.value > 0
    ? Math.min(100, Math.round((totalCalculatedSavings.value / totalTargetSavings.value) * 100))
    : 0
);

const chartMax = computed(() =>
  Math.max(1, ...props.savingsHistory.map((r) => r.totaal))
);

function potFor(goal: SavingsGoal) {
  return isPotGoal(goal) && props.currentMonth
    ? computePotSettlement(goal, props.currentMonth, props.transactions)
    : null;
}

function potStatus(goal: SavingsGoal) {
  const settlement = potFor(goal);
  return settlement ? potCompensationStatus(settlement) : null;
}

function barHeight(totaal: number) {
  return `${Math.max(4, Math.round((totaal / chartMax.value) * 100))}%`;
}
</script>

<template>
  <div id="savings-tracker-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <PiggyBank class="w-5 h-5 text-indigo-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">Spaarrekeningen & Spaardoelen</h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Beheer meerdere spaarrekeningen met automatische IBAN-mutatietoewijzing vanuit de betaalrekening
        </p>
      </div>

      <div class="flex items-center gap-3">
        <div class="bg-slate-800 border border-slate-700 px-3.5 py-2 rounded-xl text-right">
          <span class="text-[10px] text-slate-400 block font-medium">Totaal Spaarsaldo:</span>
          <span class="text-base font-bold font-mono text-emerald-400">
            € {{ totalCalculatedSavings.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
          </span>
        </div>

        <button
          id="savings-add-goal-btn"
          type="button"
          class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2.5 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
          @click="onOpenAddGoal"
        >
          <Plus class="w-4 h-4" />
          <span>Spaarrekening Toevoegen</span>
        </button>
      </div>
    </div>

    <div
      class="bg-gradient-to-r from-slate-900 via-indigo-950/30 to-slate-900 border border-indigo-900/50 p-4 rounded-2xl flex items-start gap-3 text-xs"
    >
      <div
        class="w-8 h-8 rounded-xl bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-indigo-400 shrink-0 mt-0.5"
      >
        <Info class="w-4 h-4" />
      </div>
      <div class="space-y-1">
        <h4 class="font-semibold text-white">
          Slimme Mapping voor Meerdere Spaarrekeningen (EnableBanking Beperking Opgelost)
        </h4>
        <p class="text-slate-400 leading-relaxed text-[11px]">
          Omdat de gratis licentie van EnableBanking beperkt is tot de hoofdbetaalrekening, koppel je
          hieronder je externe spaarrekeningen met hun IBAN-nummer. Zodra er een overboeking plaatsvindt
          vanaf je ING betaalrekening naar één van deze spaarrekeningen, wordt de mutatie
          <strong class="text-slate-200">volledig automatisch</strong> herkend, bijgeteld bij het betreffende
          spaardoel en direct verwerkt in de maandbegroting!
        </p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="goal in goalsWithCalculations"
        :key="goal.id"
        class="bg-slate-900 border border-slate-800 hover:border-slate-700/80 p-5 rounded-2xl shadow-sm transition-all flex flex-col justify-between"
      >
        <div>
          <div class="flex items-start justify-between gap-2 mb-3">
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 shrink-0"
              >
                <component :is="getGoalIcon(goal.iconName)" class="w-5 h-5" />
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h4 class="font-bold text-white text-sm line-clamp-1">{{ goal.name }}</h4>
                  <span
                    v-if="potFor(goal)"
                    class="text-[9px] bg-amber-950 text-amber-300 border border-amber-800 px-1.5 py-0.5 rounded font-semibold uppercase"
                  >
                    Potje
                  </span>
                </div>
                <span class="text-[11px] text-slate-400 block">{{ goal.bankName }}</span>
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button
                type="button"
                class="p-1 text-slate-400 hover:text-indigo-300 hover:bg-slate-800 rounded transition-colors"
                title="Bewerken"
                @click="onEditGoal(goal)"
              >
                <Edit2 class="w-3.5 h-3.5" />
              </button>
              <button
                type="button"
                class="p-1 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded transition-colors"
                title="Verwijderen"
                @click="onDeleteGoal(goal.id)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <div
            class="bg-slate-800/80 border border-slate-700/80 px-2.5 py-1.5 rounded-lg space-y-1 text-[11px] text-slate-300 mb-3"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="text-slate-400 text-[10px]">Omschrijving</span>
              <span class="font-semibold text-white truncate">{{ goal.name }}</span>
            </div>
            <div class="flex items-center justify-between gap-2 font-mono">
              <span class="text-slate-400 text-[10px]">IBAN</span>
              <span class="font-bold text-slate-200">{{ goal.accountIban || "Geen eigen IBAN" }}</span>
            </div>
            <div v-if="potFor(goal)?.budgetItems.length" class="flex items-start justify-between gap-2">
              <span class="text-slate-400 text-[10px] shrink-0 pt-0.5">Rubriek</span>
              <span class="font-semibold text-white text-right">
                <template v-if="potFor(goal)!.budgetItems.length === 1">
                  {{ potFor(goal)!.budgetItems[0].group }} › {{ potFor(goal)!.budgetItems[0].name }}
                </template>
                <template v-else>
                  {{ potFor(goal)!.budgetItems.length }} rubrieken:
                  {{
                    potFor(goal)!
                      .budgetItems.map((item) => item.name)
                      .join(", ")
                  }}
                </template>
              </span>
            </div>
          </div>

          <div
            v-if="potFor(goal)"
            class="mb-3 bg-amber-950/30 border border-amber-800/50 rounded-xl px-3 py-2.5 space-y-2 text-[11px] font-mono"
          >
            <div
              class="flex items-center justify-between gap-2 rounded-lg px-2 py-1.5 border"
              :class="
                potStatus(goal)?.sufficient
                  ? 'bg-emerald-950/40 border-emerald-800/60 text-emerald-300'
                  : 'bg-rose-950/30 border-rose-800/50 text-rose-300'
              "
            >
              <span class="font-sans font-semibold text-[10px] uppercase tracking-wide">
                {{ potStatus(goal)?.sufficient ? "Voldoende gecompenseerd" : "Nog niet voldoende" }}
              </span>
              <CheckCircle2
                v-if="potStatus(goal)?.sufficient"
                class="w-4 h-4 text-emerald-400 shrink-0"
              />
            </div>

            <div class="flex justify-between text-slate-300">
              <span>Begroot / in pot</span>
              <span>
                € {{ potFor(goal)!.budgeted.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
            <button
              type="button"
              class="w-full flex justify-between text-rose-300 hover:text-rose-200 transition-colors group"
              @click="openPotDetail(goal)"
            >
              <span class="group-hover:underline">Uitgegeven (bank)</span>
              <span class="flex items-center gap-1.5">
                <span>
                  € {{ potFor(goal)!.spent.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
                <span
                  v-if="potFor(goal)!.spentTransactions.length > 0"
                  class="text-[9px] text-slate-500 group-hover:text-slate-400"
                >
                  ({{ potFor(goal)!.spentTransactions.length }})
                </span>
              </span>
            </button>
            <div class="flex justify-between text-emerald-300/90">
              <span>Gecompenseerd</span>
              <span>
                €
                {{ potFor(goal)!.compensated.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
            <div
              v-if="!potStatus(goal)?.sufficient"
              class="flex justify-between font-bold pt-1 border-t border-amber-800/40 text-yellow-400"
            >
              <span>Nog te compenseren</span>
              <span>
                €
                {{ potStatus(goal)!.shortfall.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
          </div>

          <div v-if="!goal.isPot" class="mt-3">
            <div class="flex items-baseline justify-between gap-2">
              <div>
                <span class="text-xs text-slate-400">Huidig Saldo:</span>
              </div>
              <span
                class="text-xl font-black font-mono"
                :class="goal.currentBalance >= 0 ? 'text-emerald-400' : 'text-rose-400'"
              >
                € {{ goal.currentBalance.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>

            <div
              class="mt-2 bg-slate-800/60 border border-slate-700/60 rounded-lg px-2.5 py-2 space-y-0.5 text-[10px] font-mono text-slate-400"
            >
              <div class="flex justify-between">
                <span>Beginsaldo</span>
                <span class="text-slate-300">
                  € {{ goal.initialAmount.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
              <div v-if="goal.totalDeposits > 0" class="flex justify-between text-emerald-400/90">
                <span>+ Stortingen (Naar)</span>
                <span>
                  € {{ goal.totalDeposits.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
              <div v-if="goal.totalWithdrawals > 0" class="flex justify-between text-rose-400/90">
                <span>− Opnames (Van)</span>
                <span>
                  € {{ goal.totalWithdrawals.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
            </div>

            <div class="w-full bg-slate-800 h-2 rounded-full overflow-hidden my-2.5">
              <div
                class="bg-emerald-500 h-full rounded-full transition-all"
                :style="{ width: `${goal.progressPercent}%` }"
              />
            </div>

            <div class="flex items-center justify-between text-[11px] text-slate-400 font-mono">
              <span>Doel: €{{ goal.targetAmount.toLocaleString("nl-NL") }}</span>
              <span class="font-bold text-slate-300">{{ goal.progressPercent }}%</span>
            </div>
          </div>

          <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-between text-[11px]">
            <span class="text-slate-400">Maandelijkse inleg:</span>
            <span class="font-mono font-semibold text-slate-200">
              € {{ goal.monthlyContribution.toFixed(2) }} / mnd
            </span>
          </div>

          <p v-if="goal.notes" class="mt-2 text-[10px] text-slate-400 italic truncate">{{ goal.notes }}</p>
        </div>

        <div v-if="!goal.isPot" class="mt-4 pt-3 border-t border-slate-800">
          <button
            type="button"
            class="w-full flex items-center justify-between text-[11px] text-indigo-400 hover:text-indigo-300 font-medium py-1"
            @click="expandedGoalId = expandedGoalId === goal.id ? null : goal.id"
          >
            <span class="flex items-center gap-1.5">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-400" />
              <span>{{ goal.transactions.length }} stortingen (Naar)</span>
            </span>
            <ChevronUp v-if="expandedGoalId === goal.id" class="w-3.5 h-3.5" />
            <ChevronDown v-else class="w-3.5 h-3.5" />
          </button>

          <div v-if="expandedGoalId === goal.id" class="mt-2 space-y-1.5 max-h-36 overflow-y-auto pr-1">
            <p
              v-if="goal.transactions.length === 0 && goal.withdrawalTxs.length === 0"
              class="text-[10px] text-slate-500 italic py-1 text-center"
            >
              Nog geen bankmutaties gematcht — saldo = beginsaldo.
            </p>
            <div
              v-for="tx in goal.transactions"
              :key="tx.id"
              class="bg-slate-800/60 p-2 rounded-lg text-[10px] flex items-center justify-between font-mono"
            >
              <div class="truncate max-w-[150px]">
                <TransactionDate :date="tx.date" :time="tx.time" size="sm" />
                <span class="text-slate-200 truncate">{{ tx.description }}</span>
              </div>
              <span class="font-bold text-emerald-400 shrink-0 ml-2">
                +€ {{ savingsBalanceDelta(tx).toFixed(2) }}
              </span>
            </div>
            <div
              v-for="tx in goal.withdrawalTxs"
              :key="`w-${tx.id}`"
              class="bg-slate-800/60 p-2 rounded-lg text-[10px] flex items-center justify-between font-mono"
            >
              <div class="truncate max-w-[150px]">
                <TransactionDate :date="tx.date" :time="tx.time" size="sm" />
                <span class="text-slate-200 truncate">{{ tx.description }}</span>
              </div>
              <span class="font-bold text-rose-400 shrink-0 ml-2">
                −€ {{ Math.abs(savingsBalanceDelta(tx)).toFixed(2) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2 bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="font-bold text-white text-base">Spaarsaldo Groeicurve 2026</h3>
            <p class="text-xs text-slate-400">Cumulatief verloop van alle gekoppelde spaarrekeningen</p>
          </div>
          <span
            class="text-xs font-mono text-emerald-400 bg-emerald-950 px-2.5 py-1 rounded-lg border border-emerald-800 font-bold"
          >
            Totaal Doel € {{ totalTargetSavings.toLocaleString("nl-NL") }} ({{ overallProgress }}%)
          </span>
        </div>

        <div class="h-64 w-full flex items-end gap-1.5 sm:gap-2 pt-2">
          <div
            v-for="row in savingsHistory"
            :key="row.monthId"
            class="flex-1 flex flex-col items-center gap-1 min-w-0 h-full justify-end"
            :title="`${row.month}: € ${row.totaal.toLocaleString('nl-NL')}`"
          >
            <span class="text-[9px] text-emerald-300/80 font-mono truncate max-w-full">
              {{ row.totaal > 0 ? `€${Math.round(row.totaal / 1000)}k` : "" }}
            </span>
            <div class="w-full flex-1 flex items-end justify-center">
              <div
                class="w-4/5 max-w-[20px] bg-gradient-to-t from-emerald-600/80 to-emerald-400 rounded-t shadow-sm shadow-emerald-500/20"
                :style="{ height: barHeight(row.totaal) }"
              />
            </div>
            <span class="text-[10px] text-slate-400 font-medium">{{ row.month.slice(0, 3) }}</span>
          </div>
        </div>
      </div>

      <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col justify-between">
        <div>
          <h3 class="font-bold text-white text-base mb-1">Spaarplan Samenvatting</h3>
          <p class="text-xs text-slate-400 mb-4">
            Verdeling over de {{ savingsGoals.length }} actieve spaarrekeningen
          </p>

          <div class="space-y-3">
            <div
              v-for="goal in goalsWithCalculations.filter((g) => !g.isPot)"
              :key="goal.id"
              class="p-2.5 bg-slate-800/60 rounded-xl border border-slate-700/60"
            >
              <div class="flex items-center justify-between mb-1">
                <div class="flex items-center gap-2">
                  <component :is="getGoalIcon(goal.iconName)" class="w-3.5 h-3.5 text-indigo-400" />
                  <span class="text-xs font-semibold text-white truncate max-w-[130px]">{{ goal.name }}</span>
                </div>
                <span class="text-xs font-mono font-bold text-emerald-400">
                  €{{ goal.currentBalance.toFixed(0) }}
                </span>
              </div>
              <div class="w-full bg-slate-700/60 h-1.5 rounded-full overflow-hidden my-1">
                <div
                  class="bg-emerald-500 h-full rounded-full transition-all"
                  :style="{ width: `${goal.progressPercent}%` }"
                />
              </div>
              <div class="flex justify-between text-[10px] text-slate-400 font-mono">
                <span>Doel: €{{ goal.targetAmount }}</span>
                <span>{{ goal.progressPercent }}%</span>
              </div>
            </div>
          </div>
        </div>

        <button
          type="button"
          class="mt-4 w-full bg-slate-800 hover:bg-slate-750 text-slate-200 hover:text-white py-2 rounded-xl text-xs font-semibold border border-slate-700 transition-colors flex items-center justify-center gap-1.5"
          @click="onOpenAddGoal"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>Nieuw Spaardoel Toevoegen</span>
        </button>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
      <div class="bg-slate-850 px-5 py-3.5 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400" />
          <h3 class="font-bold text-white text-sm tracking-wider uppercase">
            JAAROVERZICHT SPAARREKENING 2026 (PDF PAGINA 2)
          </h3>
        </div>
        <span class="text-xs text-slate-400 font-mono">12 Maanden Overzicht</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs font-mono">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-950/40 text-slate-400 font-sans font-semibold">
              <th class="py-3 px-4">Maand</th>
              <th class="py-3 px-4 text-right">Op rekening</th>
              <th class="py-3 px-4 text-right">Sparen (Maandelijks)</th>
              <th class="py-3 px-4 text-right">Extra (Inleg)</th>
              <th class="py-3 px-4 text-right">Opgenomen</th>
              <th class="py-3 px-4 text-right font-bold text-emerald-400">Totaal Spaarsaldo</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr
              v-for="row in savingsHistory"
              :key="row.monthId"
              class="hover:bg-slate-800/40 transition-colors"
            >
              <td class="py-3 px-4 font-sans font-bold text-white">{{ row.month }}</td>
              <td class="py-3 px-4 text-right text-slate-300">
                € {{ row.opRekening.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-right text-slate-300">
                € {{ row.sparen.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-4 text-right text-emerald-400 font-semibold">
                {{
                  row.extra > 0
                    ? `+€ ${row.extra.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}`
                    : "€ 0,00"
                }}
              </td>
              <td class="py-3 px-4 text-right text-rose-400 font-semibold">
                {{
                  row.opgenomen > 0
                    ? `-€ ${row.opgenomen.toLocaleString("nl-NL", { minimumFractionDigits: 2 })}`
                    : "€ 0,00"
                }}
              </td>
              <td class="py-3 px-4 text-right font-bold text-emerald-400 text-sm">
                € {{ row.totaal.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <PotSettlementModal
    v-if="currentMonth"
    :is-open="potDetailGoalId !== null"
    :on-close="closePotDetail"
    :goal="potDetailGoal"
    :settlement="potDetailSettlement"
    :current-month="currentMonth"
  />
</template>
