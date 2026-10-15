<script setup lang="ts">
import { computed } from "vue";
import { ArrowUpRight, Clock, TrendingDown } from "lucide-vue-next";
import type { BudgetItem } from "../types";
import { computeDeferredPaymentKpi, type MonthKpiSnapshot } from "../monthKpi";
import ExpenseBudgetSplit from "./ExpenseBudgetSplit.vue";

const props = withDefaults(defineProps<{
  expenseItems: BudgetItem[];
  kpi: MonthKpiSnapshot;
  toCompensate?: number;
  compact?: boolean;
}>(), {
  toCompensate: 0,
  compact: false,
});

const emit = defineEmits<{
  openExpenses: [];
  openDeferred: [];
}>();

const deferred = computed(() => computeDeferredPaymentKpi(props.expenseItems));

function euro(amount: number): string {
  return amount.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
</script>

<template>
  <section
    aria-label="Uitgaven en vooruit uitgegeven"
    class="min-w-0 overflow-hidden rounded-2xl border border-rose-500/25 bg-slate-900 shadow-sm md:col-span-2"
  >
    <div class="grid h-full grid-cols-1 sm:grid-cols-2">
      <button
        type="button"
        class="flex min-w-0 flex-col text-left transition-colors hover:bg-slate-800/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-400"
        :class="compact ? 'p-3.5' : 'p-5'"
        @click="emit('openExpenses')"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <span class="text-xs font-semibold text-slate-300">Totaal uitgaven</span>
          <TrendingDown class="h-4 w-4 shrink-0 text-rose-400" aria-hidden="true" />
        </div>
        <div
          class="font-mono font-bold tracking-tight text-rose-400"
          :class="compact ? 'text-lg' : 'text-2xl'"
        >
          € {{ euro(kpi.totalExpenseBudget) }}
        </div>
        <p class="mt-0.5 text-[10px] text-slate-500">{{ kpi.expenseBudgetedCount }} begrote posten</p>

        <ExpenseBudgetSplit
          class="mt-3"
          :fixed-amount="kpi.totalExpenseFixedBudget"
          :month-amount="kpi.totalExpenseRulesBudget"
        />

        <div class="mt-3 space-y-1 border-t border-slate-800/80 pt-3 text-[11px]">
          <div class="flex flex-wrap items-baseline justify-between gap-x-2">
            <span class="text-slate-400">Betaald</span>
            <span class="font-mono text-rose-300">€ {{ euro(kpi.totalExpensePaid) }}</span>
          </div>
          <div class="flex flex-wrap items-baseline justify-between gap-x-2">
            <span class="text-slate-400">Nog te betalen</span>
            <span class="font-mono" :class="kpi.totalExpenseRemaining > 0 ? 'text-amber-400' : 'text-slate-500'">
              € {{ euro(kpi.totalExpenseRemaining) }}
            </span>
          </div>
          <div v-if="toCompensate > 0" class="flex flex-wrap items-baseline justify-between gap-x-2">
            <span class="text-slate-400">Nog te compenseren</span>
            <span class="font-mono text-amber-300">€ {{ euro(toCompensate) }}</span>
          </div>
          <div v-if="kpi.totalExpenseOver > 0" class="flex flex-wrap items-baseline justify-between gap-x-2 border-t border-slate-800/70 pt-1.5">
            <span class="text-slate-400">Overschrijding</span>
            <span class="font-mono text-rose-400">€ {{ euro(kpi.totalExpenseOver) }}</span>
          </div>
        </div>
        <span class="mt-auto flex items-center gap-1 pt-3 text-[10px] text-slate-400">
          Bekijk alle uitgaven <ArrowUpRight class="h-3 w-3" aria-hidden="true" />
        </span>
      </button>

      <button
        type="button"
        class="flex min-w-0 flex-col border-t border-rose-500/20 bg-rose-500/[0.03] text-left transition-colors hover:bg-rose-500/[0.07] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-rose-400 sm:border-l sm:border-t-0"
        :class="compact ? 'p-3.5' : 'p-5'"
        @click="emit('openDeferred')"
      >
        <div class="mb-2 flex items-center justify-between gap-2">
          <span class="text-xs font-semibold text-slate-300">Vooruit uitgegeven</span>
          <Clock class="h-4 w-4 shrink-0 text-rose-400" aria-hidden="true" />
        </div>
        <p class="mb-3 flex items-start gap-1.5 text-[10px] leading-relaxed text-rose-300">
          <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400" aria-hidden="true"></span>
          Al opgenomen in Totaal uitgaven
        </p>
        <div
          class="font-mono font-bold tracking-tight"
          :class="[compact ? 'text-lg' : 'text-2xl', deferred.remaining > 0 ? 'text-rose-400' : 'text-emerald-400']"
        >
          € {{ euro(deferred.remaining) }}
        </div>
        <p class="mt-0.5 text-[10px] text-slate-400">Nog te betalen · Klarna + creditcard</p>

        <div class="mt-3 space-y-1 border-t border-rose-500/15 pt-3 text-[11px]">
          <div class="flex flex-wrap items-baseline justify-between gap-x-2">
            <span class="text-slate-400">Deze maand</span>
            <span class="font-mono text-slate-300">€ {{ euro(deferred.budget) }}</span>
          </div>
          <div class="flex flex-wrap items-baseline justify-between gap-x-2">
            <span class="text-slate-400">Afgelost</span>
            <span class="font-mono text-emerald-400">€ {{ euro(deferred.paid) }}</span>
          </div>
          <div v-if="deferred.over > 0" class="flex flex-wrap items-baseline justify-between gap-x-2">
            <span class="text-slate-400">Extra afgelost</span>
            <span class="font-mono text-rose-300">+€ {{ euro(deferred.over) }}</span>
          </div>
        </div>
        <span class="mt-auto flex items-center gap-1 pt-3 text-[10px] text-slate-400">
          Bekijk aflossingen <ArrowUpRight class="h-3 w-3" aria-hidden="true" />
        </span>
      </button>
    </div>
  </section>
</template>
