<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { AlertTriangle, Clock, TrendingDown } from "lucide-vue-next";
import type { BudgetItem } from "../types";
import {
  buildExpenseModalBreakdown,
  computeDeferredPaymentKpi,
  computeMonthKpi,
  type ExpenseDetailView,
} from "../monthKpi";
import ExpenseBudgetSplit from "./ExpenseBudgetSplit.vue";
import KpiBreakdownModal from "./KpiBreakdownModal.vue";

const props = withDefaults(defineProps<{
  isOpen: boolean;
  expenseItems: BudgetItem[];
  initialView?: ExpenseDetailView;
  onClose: () => void;
  onOpenItem?: (item: BudgetItem) => void;
}>(), {
  initialView: "all",
});

const activeView = ref<ExpenseDetailView>(props.initialView);

watch(
  () => [props.isOpen, props.initialView] as const,
  ([isOpen, initialView]) => {
    if (isOpen) {
      activeView.value = initialView;
    }
  }
);

const breakdown = computed(() =>
  buildExpenseModalBreakdown(props.expenseItems, activeView.value, props.onOpenItem)
);
const expenseKpi = computed(() => computeMonthKpi({
  incomeItems: [],
  expenseItems: props.expenseItems,
  savingsItems: [],
  bankBalance: 0,
}));
const deferred = computed(() => computeDeferredPaymentKpi(props.expenseItems));

function euro(amount: number): string {
  return amount.toLocaleString("nl-NL", { style: "currency", currency: "EUR" });
}
</script>

<template>
  <KpiBreakdownModal
    :is-open="isOpen"
    title="Uitgaven · details"
    :formula="breakdown.formula"
    :subtitle="breakdown.subtitle"
    :columns="breakdown.columns"
    :rows="breakdown.rows"
    :totals="breakdown.totals"
    :total-value="breakdown.totalValue"
    total-label="Totaal"
    :total-color-class="breakdown.totalColorClass"
    :empty-message="breakdown.emptyMessage"
    :on-close="onClose"
    wide
  >
    <template #summary>
      <div class="grid grid-cols-2 gap-3 sm:grid-cols-3" aria-label="Uitgavenoverzichten">
        <button
          type="button"
          class="col-span-2 min-w-0 rounded-xl border p-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 sm:col-span-1"
          :class="activeView === 'all' ? 'border-rose-400/70 bg-rose-500/10' : 'border-slate-700 bg-slate-950/40 hover:border-rose-400/50'"
          :aria-pressed="activeView === 'all'"
          @click="activeView = 'all'"
        >
          <span class="flex items-center justify-between gap-2 text-xs font-semibold text-slate-200">
            Alle uitgaven <TrendingDown class="h-4 w-4 shrink-0 text-rose-400" aria-hidden="true" />
          </span>
          <span class="mt-2 block font-mono text-lg font-bold text-rose-300">{{ euro(expenseKpi.totalExpenseBudget) }}</span>
          <ExpenseBudgetSplit
            class="mt-2"
            :fixed-amount="expenseKpi.totalExpenseFixedBudget"
            :month-amount="expenseKpi.totalExpenseRulesBudget"
          />
        </button>

        <button
          type="button"
          class="flex min-w-0 flex-col rounded-xl border p-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400"
          :class="activeView === 'deferred' ? 'border-rose-400/70 bg-rose-500/10' : 'border-rose-500/25 bg-rose-500/[0.03] hover:border-rose-400/50'"
          :aria-pressed="activeView === 'deferred'"
          @click="activeView = 'deferred'"
        >
          <span class="flex items-start justify-between gap-2 text-xs font-semibold text-slate-200">
            Vooruit uitgegeven <Clock class="h-4 w-4 shrink-0 text-rose-400" aria-hidden="true" />
          </span>
          <span class="mt-2 block font-mono text-lg font-bold text-rose-300">{{ euro(deferred.remaining) }}</span>
          <span class="mt-1 block text-[11px] text-slate-400">Nog te betalen · Klarna + creditcard</span>
          <span class="mt-auto block pt-2 text-[10px] leading-relaxed text-rose-300">Al opgenomen in Alle uitgaven</span>
        </button>

        <button
          type="button"
          class="flex min-w-0 flex-col rounded-xl border p-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400"
          :class="activeView === 'over' ? 'border-rose-400/70 bg-rose-500/15' : expenseKpi.totalExpenseOver > 0 ? 'border-rose-500/50 bg-rose-500/10 hover:border-rose-400' : 'border-slate-700 bg-slate-950/40 hover:border-slate-500'"
          :aria-pressed="activeView === 'over'"
          @click="activeView = 'over'"
        >
          <span class="flex items-start justify-between gap-2 text-xs font-semibold text-slate-200">
            Overschrijdingen <AlertTriangle class="h-4 w-4 shrink-0" :class="expenseKpi.totalExpenseOver > 0 ? 'text-rose-400' : 'text-slate-500'" aria-hidden="true" />
          </span>
          <span class="mt-2 block font-mono text-lg font-bold" :class="expenseKpi.totalExpenseOver > 0 ? 'text-rose-300' : 'text-slate-400'">{{ euro(expenseKpi.totalExpenseOver) }}</span>
          <span class="mt-1 block text-[11px] text-slate-400">Betaald boven budget</span>
          <span class="mt-auto block pt-2 text-[10px] leading-relaxed" :class="expenseKpi.totalExpenseOver > 0 ? 'text-rose-300' : 'text-slate-500'">
            {{ expenseKpi.totalExpenseOver > 0 ? 'Extra kosten buiten de begroting' : 'Geen overschrijdingen deze maand' }}
          </span>
        </button>
      </div>
    </template>
  </KpiBreakdownModal>
</template>
