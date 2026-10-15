<script setup lang="ts">
import { computed } from "vue";
import { isFixedBudgetItem, isMonthEntryBudgetItem } from "../kpiBreakdown";
import type { BudgetItem } from "../types";

const props = defineProps<{
  item: BudgetItem;
}>();

const badge = computed(() => {
  if (isFixedBudgetItem(props.item)) {
    return { label: "Vast", color: "border-blue-800/60 bg-blue-950/50 text-blue-300" };
  }
  if (isMonthEntryBudgetItem(props.item)) {
    return { label: "Los deze maand", color: "border-violet-800/60 bg-violet-950/50 text-violet-300" };
  }
  return { label: "Niet begroot", color: "border-slate-700/70 bg-slate-800/60 text-slate-400" };
});
</script>

<template>
  <span
    class="inline-flex items-center whitespace-nowrap rounded-md border px-1.5 py-0.5 text-[10px] font-semibold"
    :class="badge.color"
  >
    {{ badge.label }}
  </span>
</template>
