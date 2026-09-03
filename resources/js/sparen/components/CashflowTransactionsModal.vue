<script setup lang="ts">
import { computed } from "vue";
import { ArrowLeftRight, X } from "lucide-vue-next";
import type { Transaction } from "../types";
import TransactionDate from "./TransactionDate.vue";

const props = defineProps<{
  isOpen: boolean;
  title: string;
  subtitle?: string;
  transactions: Transaction[];
  onClose: () => void;
  onOpenInTransactions?: () => void;
}>();

function euro(n: number): string {
  return n.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const total = computed(() =>
  props.transactions.reduce((sum, tx) => sum + tx.amount, 0)
);
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/75 backdrop-blur-sm"
    @click="onClose"
  >
    <div
      class="bg-slate-900 border border-slate-800 w-full sm:max-w-2xl sm:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col"
      @click.stop
    >
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3 shrink-0">
        <div class="flex items-start gap-3 min-w-0">
          <div class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
            <ArrowLeftRight class="w-4 h-4" />
          </div>
          <div class="min-w-0">
            <h3 class="font-bold text-white text-base">{{ title }}</h3>
            <p v-if="subtitle" class="text-xs text-slate-400 mt-1">{{ subtitle }}</p>
            <p class="text-[11px] text-slate-500 mt-1 font-mono">
              {{ transactions.length }} mutaties · saldo-effect
              {{ total >= 0 ? "+" : "−" }}€ {{ euro(Math.abs(total)) }}
            </p>
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
        <p v-if="transactions.length === 0" class="px-5 py-10 text-center text-sm text-slate-400">
          Geen mutaties in deze emmer voor deze periode.
        </p>
        <ul v-else class="divide-y divide-slate-800/70">
          <li
            v-for="tx in transactions"
            :key="tx.id"
            class="px-5 py-3 flex items-start justify-between gap-3"
          >
            <div class="min-w-0">
              <TransactionDate :date="tx.date" :time="tx.time" />
              <p class="text-sm text-white truncate">{{ tx.description }}</p>
              <p v-if="tx.counterparty" class="text-[11px] text-slate-400 truncate">
                {{ tx.counterparty }}
              </p>
            </div>
            <span
              class="font-mono text-sm font-bold shrink-0"
              :class="tx.amount >= 0 ? 'text-emerald-400' : 'text-rose-400'"
            >
              {{ tx.amount >= 0 ? "+" : "−" }}€ {{ euro(Math.abs(tx.amount)) }}
            </span>
          </li>
        </ul>
      </div>

      <div
        v-if="onOpenInTransactions"
        class="px-5 py-3 border-t border-slate-800 flex justify-end shrink-0"
      >
        <button
          type="button"
          class="text-xs font-semibold bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded-lg"
          @click="onOpenInTransactions"
        >
          Open in Transacties
        </button>
      </div>
    </div>
  </div>
</template>
