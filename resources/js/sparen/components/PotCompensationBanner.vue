<script setup lang="ts">
import { computed } from "vue";
import { PiggyBank } from "lucide-vue-next";
import type { PotCompensationNeed } from "../potSettlement";

const props = defineProps<{
  needs: PotCompensationNeed[];
  monthName: string;
}>();

const emit = defineEmits<{
  select: [need: PotCompensationNeed];
}>();

function euro(n: number) {
  return n.toLocaleString("nl-NL", { minimumFractionDigits: 2 });
}

const total = computed(() => props.needs.reduce((sum, row) => sum + row.shortfall, 0));
</script>

<template>
  <div
    v-if="needs.length > 0"
    class="bg-amber-950/40 border border-amber-700/50 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center gap-3"
    role="status"
  >
    <div
      class="w-10 h-10 rounded-xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-amber-300 shrink-0"
    >
      <PiggyBank class="w-5 h-5" />
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex items-baseline justify-between gap-3">
        <p class="text-sm font-bold text-amber-200">
          Nog te compenseren · {{ monthName }}
        </p>
        <p class="text-sm font-mono font-black text-amber-300 shrink-0">
          € {{ euro(total) }}
        </p>
      </div>
      <p class="text-xs text-amber-200/80 mt-0.5">
        Uitgaven uit potjes die nog niet teruggestort zijn naar de betaalrekening.
      </p>
      <div class="mt-2 flex flex-wrap gap-1.5">
        <button
          v-for="need in needs"
          :key="need.goal.id"
          type="button"
          class="inline-flex items-center gap-1.5 text-[11px] font-mono bg-amber-900/50 hover:bg-amber-800/60 border border-amber-700/40 text-amber-100 px-2 py-1 rounded-lg transition-colors"
          @click="emit('select', need)"
        >
          <span class="font-sans font-medium truncate max-w-[10rem]">{{ need.goal.name }}</span>
          <span class="font-bold">€ {{ euro(need.shortfall) }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
