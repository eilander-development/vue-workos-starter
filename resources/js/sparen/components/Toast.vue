<script setup lang="ts">
import { AlertCircle, CheckCircle2, Info, X } from "lucide-vue-next";
import type { Component } from "vue";
import type { ToastMessage, ToastVariant } from "../composables/useToasts";

defineOptions({ name: "ToastStack" });

defineProps<{
  toasts: ToastMessage[];
}>();

const emit = defineEmits<{
  dismiss: [id: number];
}>();

const VARIANT_STYLES: Record<
  ToastVariant,
  { border: string; icon: string; Icon: Component }
> = {
  success: { border: "border-emerald-600/60", icon: "text-emerald-400", Icon: CheckCircle2 },
  error: { border: "border-rose-600/60", icon: "text-rose-400", Icon: AlertCircle },
  info: { border: "border-indigo-500/60", icon: "text-indigo-400", Icon: Info },
};
</script>

<template>
  <div
    v-if="toasts.length > 0"
    id="toast-stack"
    role="status"
    aria-live="polite"
    class="fixed z-[60] top-16 sm:top-20 right-3 sm:right-5 left-3 sm:left-auto flex flex-col gap-2 sm:w-80 pointer-events-none"
  >
    <div
      v-for="toast in toasts"
      :key="toast.id"
      class="pointer-events-auto bg-slate-900/95 backdrop-blur-sm border rounded-xl shadow-xl shadow-black/40 p-3 flex items-start gap-2.5 animate-in fade-in slide-in-from-bottom-2"
      :class="VARIANT_STYLES[toast.variant].border"
    >
      <component
        :is="VARIANT_STYLES[toast.variant].Icon"
        class="w-4 h-4 shrink-0 mt-0.5"
        :class="VARIANT_STYLES[toast.variant].icon"
      />
      <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold text-white break-words">{{ toast.title }}</p>
        <p
          v-if="toast.description"
          class="text-[11px] text-slate-400 mt-0.5 break-words"
        >
          {{ toast.description }}
        </p>
      </div>
      <button
        type="button"
        class="text-slate-500 hover:text-white p-1 -m-1 rounded-lg hover:bg-slate-800 transition-colors shrink-0"
        title="Melding sluiten"
        @click="emit('dismiss', toast.id)"
      >
        <X class="w-3.5 h-3.5" />
      </button>
    </div>
  </div>
</template>
