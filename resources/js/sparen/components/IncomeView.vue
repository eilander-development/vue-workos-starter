<script setup lang="ts">
import { computed, ref } from "vue";
import {
  ArrowUpCircle,
  TrendingUp,
  Briefcase,
  Baby,
  Receipt,
  CheckCircle2,
  Clock,
  Plus,
  Edit2,
  HelpCircle,
  Eye,
} from "lucide-vue-next";
import type { MonthlyBudget, BudgetItem, Transaction } from "../types";
import KpiBreakdownModal from "./KpiBreakdownModal.vue";
import { budgetItemRows, sumBudgetedAmount, sumBudgetedPaid, sumBudgetedRemaining, sumBudgetedOver } from "../kpiBreakdown";

const props = withDefaults(
  defineProps<{
    currentMonth: MonthlyBudget;
    allMonths?: MonthlyBudget[];
    transactions?: Transaction[];
    onUpdateBudgetItem: (itemId: string, updates: Partial<BudgetItem>) => void;
    onOpenAddBudgetItem: () => void;
    onOpenEditBudgetItem?: (item: BudgetItem) => void;
    onOpenItemTransactions?: (item: BudgetItem) => void;
  }>(),
  {
    transactions: () => [],
  }
);

const showExplanation = ref(false);
const kpiKey = ref<"budget" | "paid" | "remaining" | "over" | null>(null);
const incomeItems = computed(() => props.currentMonth.items.filter((i) => i.type === "inkomsten"));

const totalIncomeBudget = computed(() => sumBudgetedAmount(incomeItems.value));
const totalIncomeReceived = computed(() => sumBudgetedPaid(incomeItems.value));
const totalIncomePending = computed(() => sumBudgetedRemaining(incomeItems.value));
const totalIncomeSurplus = computed(() => sumBudgetedOver(incomeItems.value));

const kpiMeta = computed(
  () =>
    ({
      budget: {
        title: `Totaal begroot — ${props.currentMonth.monthName}`,
        formula: "Som van alle inkomstenposten: kolom Begroot.",
        color: "text-white",
      },
      paid: {
        title: `Reeds bijgeschreven — ${props.currentMonth.monthName}`,
        formula: "Som van bankbedragen op inkomstenposten met begroting > 0.",
        color: "text-emerald-400",
      },
      remaining: {
        title: `Nog te ontvangen — ${props.currentMonth.monthName}`,
        formula: "Per post: max(0, begroot − bank). Alleen openstaande bronnen.",
        color: "text-amber-400",
      },
      over: {
        title: `Extra / meevallers — ${props.currentMonth.monthName}`,
        formula: "Per post: max(0, bank − begroot). Alleen boven begroting.",
        color: "text-indigo-400",
      },
    }) as const
);

const kpiBreakdown = computed(() =>
  kpiKey.value
    ? budgetItemRows(incomeItems.value, kpiKey.value, (item) => {
        kpiKey.value = null;
        props.onOpenItemTransactions?.(item);
      })
    : null
);

function itemIcon(name: string) {
  const lower = name.toLowerCase();
  if (lower.includes("salaris") || lower.includes("mark")) return Briefcase;
  if (lower.includes("kind")) return Baby;
  return Receipt;
}

function itemState(item: BudgetItem) {
  const isZeroBudget = item.actual === 0;
  const isZeroPaid = item.paidOrReceived === 0;
  const isZeroNotApplicable = isZeroBudget && isZeroPaid;
  const isReceived = !isZeroBudget && item.paidOrReceived >= item.actual;
  const isPartiallyReceived =
    !isZeroBudget && item.paidOrReceived > 0 && item.paidOrReceived < item.actual;
  const pendingAmount = Math.max(0, item.actual - item.paidOrReceived);
  const surplusAmount = Math.max(0, item.paidOrReceived - item.actual);
  return {
    isZeroNotApplicable,
    isReceived,
    isPartiallyReceived,
    pendingAmount,
    surplusAmount,
  };
}
</script>

<template>
  <div id="income-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <ArrowUpCircle class="w-5 h-5 text-emerald-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">
            Inkomsten • {{ currentMonth.monthName }} {{ currentMonth.year }}
          </h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Overzicht van salaris, kinderbijslag en overige inkomstenbronnen met live bankkoppeling
        </p>
      </div>
      <div class="flex items-center gap-2.5">
        <button
          type="button"
          class="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-xl text-xs font-medium border border-slate-700 transition-colors"
          title="Uitleg over Budget vs Betaald/Ontvangen"
          @click="showExplanation = !showExplanation"
        >
          <HelpCircle class="w-4 h-4 text-indigo-400" />
          <span>Waarom Budget vs Bank?</span>
        </button>
        <button
          type="button"
          class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition-all active:scale-95"
          @click="onOpenAddBudgetItem"
        >
          <Plus class="w-4 h-4" />
          <span>Inkomstenbron Toevoegen</span>
        </button>
      </div>
    </div>

    <div
      v-if="showExplanation"
      class="bg-indigo-950/40 border border-indigo-500/30 p-4 rounded-2xl text-xs text-slate-300 space-y-2 animate-fade-in"
    >
      <h4 class="font-bold text-white flex items-center gap-1.5">
        <TrendingUp class="w-4 h-4 text-indigo-400" />
        <span>Het verschil tussen "Budget" en "Ontvangen via rekening":</span>
      </h4>
      <ul class="list-disc list-inside space-y-1 text-slate-300 pl-1">
        <li>
          <strong class="text-white">Budget:</strong> Het bedrag dat je voor deze specifieke maand
          verwacht (bijv. € 0,00 voor Kinderbijslag in augustus, of € 299,97 in juli/oktober).
        </li>
        <li>
          <strong class="text-white">Ontvangen via bank:</strong> Het realtime bedrag dat via je
          gekoppelde bankrekening (PSD2) daadwerkelijk is bijgeschreven.
        </li>
        <li>
          <strong class="text-white">Waarom beide nuttig zijn:</strong> Zo zie je vóórdat een maand
          begint wat je cashflow zal zijn, en zie je tijdens de maand direct of alles tijdig en correct is
          binnengekomen.
        </li>
      </ul>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        @click="kpiKey = 'budget'"
      >
        <span class="text-xs text-slate-400 block font-medium">
          Totaal Begroot ({{ currentMonth.monthName }}):
        </span>
        <div class="text-xl font-bold text-white font-mono mt-1">
          € {{ totalIncomeBudget.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-slate-500 mt-0.5 block">
          {{ incomeItems.length }} inkomstenbronnen · klik voor detail
        </span>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        @click="kpiKey = 'paid'"
      >
        <span class="text-xs text-slate-400 block font-medium">Reeds Bijgeschreven (Bank):</span>
        <div class="text-xl font-bold text-emerald-400 font-mono mt-1">
          € {{ totalIncomeReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-emerald-500 mt-0.5 block">
          {{
            totalIncomeBudget > 0
              ? `${Math.round((totalIncomeReceived / totalIncomeBudget) * 100)}% ontvangen`
              : "100%"
          }}
          · klik voor detail
        </span>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        @click="kpiKey = 'remaining'"
      >
        <span class="text-xs text-slate-400 block font-medium">Nog te Ontvangen:</span>
        <div class="text-xl font-bold text-amber-400 font-mono mt-1">
          € {{ totalIncomePending.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-slate-500 mt-0.5 block">Openstaande posten · klik voor detail</span>
      </button>
      <button
        type="button"
        class="text-left bg-[#101726] border border-slate-800 hover:border-indigo-500/50 p-4 rounded-2xl transition-colors cursor-pointer"
        @click="kpiKey = 'over'"
      >
        <span class="text-xs text-slate-400 block font-medium">Extra / Meervallers:</span>
        <div class="text-xl font-bold text-indigo-400 font-mono mt-1">
          € {{ totalIncomeSurplus.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
        </div>
        <span class="text-[11px] text-slate-500 mt-0.5 block">Boven begroting · klik voor detail</span>
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div
        v-for="item in incomeItems"
        :key="item.id"
        class="bg-[#101726] border border-slate-800/90 hover:border-slate-700/80 p-5 rounded-2xl shadow-sm flex flex-col justify-between transition-all"
      >
        <div>
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0"
              >
                <component :is="itemIcon(item.name)" class="w-5 h-5" />
              </div>
              <div>
                <h4 class="font-bold text-white text-base">{{ item.name }}</h4>
                <span class="text-xs text-slate-400">
                  {{
                    item.name.toLowerCase().includes("kinderbijslag")
                      ? "Kwartaalpost (Jan, Apr, Jul, Okt)"
                      : "Maandelijkse inkomstenbron"
                  }}
                </span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span
                v-if="itemState(item).isZeroNotApplicable"
                class="text-[11px] font-medium px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 border border-slate-700/80 flex items-center gap-1.5"
              >
                <span>Geen uitkering deze maand (€0)</span>
              </span>
              <span
                v-else-if="itemState(item).isReceived"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-emerald-950/80 text-emerald-400 border border-emerald-800/80 flex items-center gap-1.5"
              >
                <CheckCircle2 class="w-3.5 h-3.5" />
                <span>Bijgeschreven via Bank</span>
              </span>
              <span
                v-else-if="itemState(item).isPartiallyReceived"
                class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-950/80 text-amber-400 border border-amber-800/80 flex items-center gap-1.5"
              >
                <Clock class="w-3.5 h-3.5" />
                <span>Deels ontvangen</span>
              </span>
              <span
                v-else
                class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-amber-950/80 text-amber-400 border border-amber-800/80 flex items-center gap-1.5"
              >
                <Clock class="w-3.5 h-3.5" />
                <span>In afwachting</span>
              </span>
              <button
                v-if="onOpenEditBudgetItem"
                type="button"
                class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
                title="Post en maandbedragen aanpassen"
                @click="onOpenEditBudgetItem(item)"
              >
                <Edit2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="bg-slate-950/50 p-3.5 rounded-xl border border-slate-800/80 space-y-2.5 font-mono text-xs">
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
                Ontvangen op rekening (Live bank):
              </span>
              <span
                class="font-bold text-sm"
                :class="item.paidOrReceived > 0 ? 'text-emerald-400' : 'text-slate-400'"
              >
                € {{ item.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </span>
            </div>
            <div
              v-if="itemState(item).pendingAmount > 0"
              class="flex justify-between items-center text-amber-400 pt-1.5 border-t border-slate-800"
            >
              <span class="font-sans font-medium">Nog te ontvangen:</span>
              <span class="font-bold">
                €
                {{
                  itemState(item).pendingAmount.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                }}
              </span>
            </div>
            <div
              v-if="itemState(item).surplusAmount > 0"
              class="flex justify-between items-center text-emerald-400 pt-1.5 border-t border-slate-800"
            >
              <span class="font-sans font-medium">Teveel / Extra ontvangen:</span>
              <span class="font-bold">
                +€
                {{
                  itemState(item).surplusAmount.toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                }}
              </span>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
          <span class="text-slate-500 font-mono text-[11px]">
            {{ item.paymentCount || (item.paidOrReceived > 0 ? 1 : 0) }} bankmutatie(s)
          </span>
          <button
            v-if="onOpenItemTransactions"
            type="button"
            class="text-indigo-400 hover:text-indigo-300 font-semibold flex items-center gap-1.5 hover:underline py-0.5 px-1 rounded transition-colors"
            :title="`Bekijk alle gekoppelde bankmutaties voor ${item.name}`"
            @click="onOpenItemTransactions(item)"
          >
            <Eye class="w-3.5 h-3.5" />
            <span>Bekijk transacties</span>
          </button>
          <button
            v-else-if="onOpenEditBudgetItem"
            type="button"
            class="text-indigo-400 hover:text-indigo-300 font-semibold flex items-center gap-1 hover:underline"
            @click="onOpenEditBudgetItem(item)"
          >
            <span>Budget/Maanden bewerken</span>
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
  </div>
</template>
