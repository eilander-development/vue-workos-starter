<script lang="ts">
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
  totalColorClass?: string;
  emptyMessage?: string;
};
</script>

<script setup lang="ts">
import { Table2, X } from "lucide-vue-next";
import type { KpiBreakdownColumn, KpiBreakdownRow } from "./KpiBreakdownModal.vue";

withDefaults(
  defineProps<{
    isOpen: boolean;
    title: string;
    formula: string;
    subtitle?: string;
    columns: KpiBreakdownColumn[];
    rows: KpiBreakdownRow[];
    totalLabel?: string;
    totalValue: number;
    totalColorClass?: string;
    emptyMessage?: string;
    onClose: () => void;
  }>(),
  {
    totalLabel: "Totaal",
    totalColorClass: "text-white",
    emptyMessage: "Geen rijen voor dit getal.",
  }
);

function euro(value: number): string {
  return value.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatCell(value: string | number): string {
  return typeof value === "number" ? `€ ${euro(value)}` : value;
}

function cellColor(column: KpiBreakdownColumn, raw: string | number, row: KpiBreakdownRow): string {
  return (
    column.colorClass?.(raw, row) ||
    (column.emphasize ? "text-white font-semibold" : "text-slate-300")
  );
}
</script>

<template>
  <div
    v-if="isOpen"
    id="kpi-breakdown-overlay"
    class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/75 backdrop-blur-sm"
    @click="onClose"
  >
    <div
      class="bg-slate-900 border border-slate-800 w-full sm:max-w-3xl sm:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col"
      @click.stop
    >
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3 shrink-0">
        <div class="flex items-start gap-3 min-w-0">
          <div class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
            <Table2 class="w-4 h-4" />
          </div>
          <div class="min-w-0">
            <h3 class="font-bold text-white text-base">{{ title }}</h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">{{ formula }}</p>
            <p v-if="subtitle" class="text-[11px] text-slate-500 mt-1">{{ subtitle }}</p>
          </div>
        </div>
        <button
          type="button"
          class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors shrink-0"
          @click="onClose"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <div class="overflow-auto flex-1">
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
              :class="
                row.onClick
                  ? 'hover:bg-slate-800/50 cursor-pointer transition-colors'
                  : 'hover:bg-slate-800/30'
              "
              @click="row.onClick?.()"
            >
              <td
                v-for="column in columns"
                :key="column.key"
                class="py-2.5 px-4"
                :class="[
                  column.align === 'right' ? 'text-right' : 'text-left',
                  column.mono || typeof row.cells[column.key] === 'number' ? 'font-mono' : '',
                  cellColor(column, row.cells[column.key], row),
                ]"
              >
                {{ formatCell(row.cells[column.key]) }}
              </td>
            </tr>
          </tbody>
          <tfoot class="border-t border-slate-700 bg-slate-950/80 sticky bottom-0">
            <tr>
              <td
                :colspan="Math.max(1, columns.length - 1)"
                class="py-3 px-4 text-xs font-semibold text-slate-300"
              >
                {{ totalLabel }} ({{ rows.length }} {{ rows.length === 1 ? "rij" : "rijen" }})
              </td>
              <td class="py-3 px-4 text-right font-mono font-bold text-sm" :class="totalColorClass">
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
  </div>
</template>
