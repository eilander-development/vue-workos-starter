<script setup lang="ts">
import { Archive, Check, LoaderCircle, X } from "lucide-vue-next";

export type BackupStep = {
  id: string;
  label: string;
  status: "pending" | "active" | "done";
};

defineProps<{
  isOpen: boolean;
  title: string;
  description?: string;
  steps: BackupStep[];
  error?: string;
  done?: boolean;
  doneLabel?: string;
}>();

const emit = defineEmits<{
  close: [];
}>();
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[85] flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <div class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
            <Archive class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">{{ title }}</h3>
            <p v-if="description" class="text-xs text-slate-400 mt-1 leading-relaxed">{{ description }}</p>
          </div>
        </div>
        <button
          v-if="done || error"
          type="button"
          class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors"
          @click="emit('close')"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <div class="px-5 py-4 space-y-3">
        <ol class="space-y-2">
          <li
            v-for="step in steps"
            :key="step.id"
            class="flex items-center gap-2.5 text-xs"
          >
            <span
              class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 border"
              :class="{
                'border-slate-700 text-slate-500': step.status === 'pending',
                'border-indigo-500 text-indigo-300': step.status === 'active',
                'border-emerald-500 bg-emerald-500/15 text-emerald-400': step.status === 'done',
              }"
            >
              <LoaderCircle v-if="step.status === 'active'" class="w-3 h-3 animate-spin" />
              <Check v-else-if="step.status === 'done'" class="w-3 h-3" />
            </span>
            <span
              :class="
                step.status === 'done'
                  ? 'text-slate-200'
                  : step.status === 'active'
                    ? 'text-white font-semibold'
                    : 'text-slate-500'
              "
            >
              {{ step.label }}
            </span>
          </li>
        </ol>

        <p v-if="error" class="text-xs text-rose-400">{{ error }}</p>
        <p v-else-if="done && doneLabel" class="text-xs text-emerald-400">{{ doneLabel }}</p>
      </div>
    </div>
  </div>
</template>
