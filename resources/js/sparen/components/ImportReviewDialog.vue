<script setup lang="ts">
import { computed } from "vue";
import { AlertTriangle, X } from "lucide-vue-next";

export type ImportTableSummary = {
  connection: string;
  table: string;
  insert: number;
  skip: number;
  conflict: number;
  protected: number;
  resolution: "live" | "backup";
  examples?: string[];
};

const props = defineProps<{
  isOpen: boolean;
  tables: ImportTableSummary[];
  busy?: boolean;
}>();

const emit = defineEmits<{
  cancel: [];
  confirm: [resolutions: Record<string, "live" | "backup">];
  update: [key: string, value: "live" | "backup"];
}>();

const needsChoice = computed(() =>
  props.tables.filter((table) => table.conflict > 0 || table.protected > 0)
);
const onlyNew = computed(() =>
  props.tables.filter((table) => table.insert > 0 && table.conflict === 0 && table.protected === 0)
);

function keyFor(table: ImportTableSummary): string {
  return `${table.connection}.${table.table}`;
}

function confirm() {
  const resolutions: Record<string, "live" | "backup"> = {};
  for (const table of props.tables) {
    resolutions[keyFor(table)] = table.resolution;
  }
  emit("confirm", resolutions);
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[80] flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <div class="p-2 rounded-xl bg-amber-500/15 text-amber-400 border border-amber-500/30 shrink-0">
            <AlertTriangle class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">Import vergelijken</h3>
            <p class="text-xs text-slate-400 mt-1 leading-relaxed">
              Nieuwe rijen worden toegevoegd. Bestaande rijen blijven. Bij verschil kies je live of backup.
            </p>
          </div>
        </div>
        <button
          type="button"
          :disabled="busy"
          class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
          @click="emit('cancel')"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <div class="px-5 py-4 space-y-4 overflow-y-auto">
        <div v-if="onlyNew.length" class="space-y-1.5">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Nieuw</p>
          <p v-for="table in onlyNew" :key="keyFor(table)" class="text-xs text-slate-300">
            {{ table.connection }}.{{ table.table }}: +{{ table.insert }}
            <span v-if="table.skip" class="text-slate-500"> · {{ table.skip }} gelijk</span>
          </p>
        </div>

        <div v-if="needsChoice.length" class="space-y-3">
          <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Twijfel</p>
          <div
            v-for="table in needsChoice"
            :key="keyFor(table)"
            class="rounded-xl border border-slate-800 bg-slate-950/60 p-3 space-y-2"
          >
            <p class="text-xs text-white font-semibold">
              {{ table.connection }}.{{ table.table }}
            </p>
            <p class="text-[11px] text-slate-400">
              +{{ table.insert }} nieuw · {{ table.skip }} gelijk ·
              {{ table.conflict }} anders
              <span v-if="table.protected"> · {{ table.protected }} beschermd</span>
            </p>
            <p v-if="table.examples?.length" class="text-[11px] text-slate-500">
              {{ table.examples.join(" · ") }}
            </p>
            <div class="flex gap-2">
              <button
                type="button"
                class="flex-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold border transition-colors"
                :class="
                  table.resolution === 'live'
                    ? 'bg-indigo-600 border-indigo-500 text-white'
                    : 'bg-slate-800 border-slate-700 text-slate-300'
                "
                @click="emit('update', keyFor(table), 'live')"
              >
                Behoud live
              </button>
              <button
                type="button"
                class="flex-1 px-3 py-1.5 rounded-lg text-[11px] font-semibold border transition-colors"
                :class="
                  table.resolution === 'backup'
                    ? 'bg-indigo-600 border-indigo-500 text-white'
                    : 'bg-slate-800 border-slate-700 text-slate-300'
                "
                @click="emit('update', keyFor(table), 'backup')"
              >
                Gebruik backup
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="px-5 py-4 border-t border-slate-800 flex items-center justify-end gap-2">
        <button
          type="button"
          :disabled="busy"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition-colors disabled:opacity-50"
          @click="emit('cancel')"
        >
          Annuleren
        </button>
        <button
          type="button"
          :disabled="busy"
          class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition-colors disabled:opacity-50"
          @click="confirm"
        >
          {{ busy ? "Bezig…" : "Importeren" }}
        </button>
      </div>
    </div>
  </div>
</template>
