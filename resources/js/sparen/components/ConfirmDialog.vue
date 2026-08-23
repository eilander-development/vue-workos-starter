<script setup lang="ts">
import { AlertTriangle, X } from "lucide-vue-next";

withDefaults(
  defineProps<{
    isOpen: boolean;
    title: string;
    description?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    busy?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
  }>(),
  {
    confirmLabel: "Verwijderen",
    cancelLabel: "Annuleren",
    busy: false,
  }
);
</script>

<template>
  <div
    v-if="isOpen"
    id="confirm-dialog-overlay"
    class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-in fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <div class="p-2 rounded-xl bg-rose-500/15 text-rose-400 border border-rose-500/30 shrink-0">
            <AlertTriangle class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">{{ title }}</h3>
            <p v-if="description" class="text-xs text-slate-400 mt-1 leading-relaxed">{{ description }}</p>
          </div>
        </div>
        <button
          type="button"
          :disabled="busy"
          class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
          @click="onCancel"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <div class="px-5 py-4 flex items-center justify-end gap-2">
        <button
          type="button"
          :disabled="busy"
          class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-medium transition-colors disabled:opacity-50"
          @click="onCancel"
        >
          {{ cancelLabel }}
        </button>
        <button
          type="button"
          :disabled="busy"
          class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold transition-colors disabled:opacity-50"
          @click="onConfirm"
        >
          {{ busy ? "Bezig…" : confirmLabel }}
        </button>
      </div>
    </div>
  </div>
</template>
