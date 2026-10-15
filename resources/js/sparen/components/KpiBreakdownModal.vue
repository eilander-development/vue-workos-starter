<script lang="ts">
import type { BudgetItem } from "../types";

export type KpiBreakdownColumn = {
  key: string;
  label: string;
  align?: "left" | "right";
  mono?: boolean;
  emphasize?: boolean;
  colorClass?: (value: number | string, row: KpiBreakdownRow) => string | undefined;
};

export type KpiBreakdownRow = {
  id: string;
  cells: Record<string, string | number>;
  onClick?: () => void;
  budgetItem?: BudgetItem;
};

export type KpiBreakdownProps = {
  isOpen: boolean;
  title: string;
  formula: string;
  subtitle?: string;
  columns: KpiBreakdownColumn[];
  rows: KpiBreakdownRow[];
  totalLabel?: string;
  totalValue: number;
  totals?: Record<string, number>;
  totalColorClass?: string;
  emptyMessage?: string;
  wide?: boolean;
};
</script>

<script setup lang="ts">
import ModalShell from "./ModalShell.vue";
import { computed, useId } from "vue";
import { ChevronRight, Table2, X } from "lucide-vue-next";
import type { KpiBreakdownColumn, KpiBreakdownRow } from "./KpiBreakdownModal.vue";
import ExpenseTypeBadge from "./ExpenseTypeBadge.vue";

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    title: string;
    formula: string;
    subtitle?: string;
    columns: KpiBreakdownColumn[];
    rows: KpiBreakdownRow[];
    totalLabel?: string;
    totalValue: number;
    totals?: Record<string, number>;
    totalColorClass?: string;
    emptyMessage?: string;
    wide?: boolean;
    onClose: () => void;
  }>(),
  {
    totalLabel: "Totaal",
    totalColorClass: "text-white",
    emptyMessage: "Geen rijen voor dit getal.",
    wide: false,
  }
);

const titleId = useId();

function euro(value: number): string {
  return value.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCell(value: string | number): string {
  if (value === "") return "";
  return typeof value === "number" ? `€ ${euro(value)}` : value;
}

function isSectionRow(row: KpiBreakdownRow): boolean {
  return row.cells.tone === "section";
}

const dataRowCount = computed(() => props.rows.filter((row) => !isSectionRow(row)).length);
const hasRowDetails = computed(() => props.rows.some((row) => !isSectionRow(row) && row.onClick));

function cellColor(column: KpiBreakdownColumn, raw: string | number, row: KpiBreakdownRow): string {
  return (
    column.colorClass?.(raw, row) ||
    (column.emphasize ? "text-white font-semibold" : "text-slate-300")
  );
}

function totalCellColor(column: KpiBreakdownColumn): string {
  const totals = props.totals ?? {};
  return column.colorClass?.(totals[column.key], { id: "total", cells: totals }) || props.totalColorClass;
}
</script>

<template>
  <ModalShell
    v-if="isOpen"
    id="kpi-breakdown-overlay"
    :on-close="onClose"
  >
    <div
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      class="bg-slate-900 border border-slate-800 w-full min-w-0 sm:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col"
      :class="wide ? 'sm:max-w-5xl' : 'sm:max-w-3xl'"
      @click.stop
    >
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3 shrink-0">
        <div class="flex items-start gap-3 min-w-0">
          <div class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
            <Table2 class="w-4 h-4" />
          </div>
          <div class="min-w-0">
            <h3 :id="titleId" class="font-bold text-white text-base">{{ title }}</h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ formula }}</p>
            <p v-if="subtitle" class="text-[11px] text-slate-500 mt-1">{{ subtitle }}</p>
          </div>
        </div>
        <button
          type="button"
          aria-label="Details sluiten"
          class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors shrink-0"
          @click="onClose"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <div class="min-h-0 overflow-auto flex-1">
        <div class="sticky left-0 border-b border-slate-800 px-4 py-4">
          <slot name="summary">
            <div
              class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-current/25 bg-current/5 p-4"
              :class="totalColorClass"
            >
              <div class="min-w-0">
                <p class="flex items-center gap-2 text-xs font-semibold text-slate-200">
                  <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" :class="totalColorClass" aria-hidden="true"></span>
                  {{ totalLabel === 'Totaal' ? title : totalLabel }}
                </p>
                <p class="mt-1.5 text-[11px] text-slate-400">
                  {{ dataRowCount }} onderliggende {{ dataRowCount === 1 ? 'post' : 'posten' }}
                </p>
              </div>
              <p class="whitespace-nowrap font-mono text-2xl font-bold tabular-nums">
                € {{ euro(totalValue) }}
              </p>
            </div>
          </slot>
          <div v-if="rows.length > 0" class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-xs font-semibold text-slate-300">Onderbouwing</h4>
            <p v-if="hasRowDetails" class="text-[11px] text-slate-400">Selecteer een post voor details</p>
          </div>
        </div>
        <div v-if="rows.length === 0" class="px-5 py-10 text-center text-sm text-slate-400">
          {{ emptyMessage }}
        </div>
        <table v-else class="w-full text-left text-xs">
          <thead class="sticky top-0 bg-slate-900/95 backdrop-blur border-b border-slate-800">
            <tr class="text-slate-400 font-semibold uppercase tracking-wider">
              <th
                v-for="column in columns"
                :key="column.key"
                class="py-2.5 px-4 whitespace-nowrap"
                :class="column.align === 'right' ? 'text-right' : 'text-left'"
              >
                {{ column.label }}
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800/70">
            <tr
              v-for="row in rows"
              :key="row.id"
              :tabindex="row.onClick ? 0 : undefined"
              class="group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-400"
              :class="
                isSectionRow(row)
                  ? 'bg-slate-800/40'
                  : row.onClick
                    ? 'hover:bg-slate-800/50 cursor-pointer transition-colors'
                    : 'hover:bg-slate-800/30'
              "
              @click="row.onClick?.()"
              @keydown.enter="row.onClick?.()"
              @keydown.space.prevent="row.onClick?.()"
            >
              <template v-if="isSectionRow(row)">
                <td
                  :colspan="columns.length"
                  class="py-2 px-4 text-[11px] font-bold uppercase tracking-wider text-slate-400"
                >
                  {{ row.cells.label }}
                </td>
              </template>
              <template v-else>
                <td
                  v-for="(column, columnIndex) in columns"
                  :key="column.key"
                  class="py-2.5 px-4"
                  :class="[
                    column.align === 'right' ? 'text-right' : 'text-left',
                    column.mono || typeof row.cells[column.key] === 'number' ? 'font-mono tabular-nums whitespace-nowrap' : '',
                    cellColor(column, row.cells[column.key], row),
                  ]"
                >
                  <div v-if="columnIndex === 0" class="flex items-center gap-2">
                    <div class="min-w-0 flex-1 space-y-1">
                      <div>{{ formatCell(row.cells[column.key]) }}</div>
                      <ExpenseTypeBadge v-if="column.key === 'name' && row.budgetItem?.type === 'uitgaven'" :item="row.budgetItem" />
                    </div>
                    <ChevronRight v-if="row.onClick" class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-colors group-hover:text-slate-200 group-focus-visible:text-slate-200" aria-hidden="true" />
                  </div>
                  <template v-else>{{ formatCell(row.cells[column.key]) }}</template>
                </td>
              </template>
            </tr>
          </tbody>
          <tfoot class="border-t border-slate-700 bg-slate-950/80 sticky bottom-0">
            <tr v-if="totals">
              <td
                v-for="(column, index) in columns"
                :key="column.key"
                class="py-3 px-4 text-xs font-semibold"
                :class="[
                  column.align === 'right' ? 'text-right' : 'text-left',
                  totals[column.key] !== undefined ? ['font-mono tabular-nums whitespace-nowrap', totalCellColor(column)] : 'text-slate-300',
                ]"
              >
                <template v-if="index === 0">
                  {{ totalLabel }} ({{ dataRowCount }} {{ dataRowCount === 1 ? "regel" : "regels" }})
                </template>
                <template v-else-if="totals[column.key] !== undefined">
                  € {{ euro(totals[column.key]) }}
                </template>
              </td>
            </tr>
            <tr v-else>
              <td
                :colspan="Math.max(1, columns.length - 1)"
                class="py-3 px-4 text-xs font-semibold text-slate-300"
              >
                {{ totalLabel }} ({{ dataRowCount }} {{ dataRowCount === 1 ? "regel" : "regels" }})
              </td>
              <td class="py-3 px-4 text-right font-mono font-bold text-sm tabular-nums whitespace-nowrap" :class="totalColorClass">
                € {{ euro(totalValue) }}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="px-5 py-3 border-t border-slate-800 flex justify-end shrink-0">
        <button
          type="button"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-medium transition-colors"
          @click="onClose"
        >
          Sluiten
        </button>
      </div>
    </div>
  </ModalShell>
</template>
