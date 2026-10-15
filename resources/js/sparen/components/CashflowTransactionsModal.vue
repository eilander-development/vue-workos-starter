<script setup lang="ts">
import ModalShell from "./ModalShell.vue";
import { computed, useId } from "vue";
import { ArrowLeftRight, ChevronRight, X } from "lucide-vue-next";
import type { Transaction } from "../types";
import TransactionDate from "./TransactionDate.vue";
import { useTransactionDetail } from "../composables/useTransactionDetail";

const props = defineProps<{
  isOpen: boolean;
  title: string;
  subtitle?: string;
  transactions: Transaction[];
  onClose: () => void;
  onOpenInTransactions?: () => void;
}>();

const { openTransactionDetail } = useTransactionDetail();
const titleId = useId();

function euro(n: number): string {
  return n.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const total = computed(() =>
  props.transactions.reduce((sum, tx) => sum + tx.amount, 0)
);
const totalColorClass = computed(() => total.value >= 0 ? "text-emerald-400" : "text-rose-400");
</script>

<template>
  <ModalShell
    v-if="isOpen"
    :on-close="onClose"
  >
    <div
      role="dialog"
      aria-modal="true"
      :aria-labelledby="titleId"
      class="bg-slate-900 border border-slate-800 w-full min-w-0 sm:max-w-3xl sm:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden max-h-[90vh] flex flex-col"
      @click.stop
    >
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3 shrink-0">
        <div class="flex items-start gap-3 min-w-0">
          <div class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
            <ArrowLeftRight class="w-4 h-4" />
          </div>
          <div class="min-w-0">
            <h3 :id="titleId" class="font-bold text-white text-base">{{ title }}</h3>
            <p v-if="subtitle" class="text-xs text-slate-400 mt-1 leading-relaxed">{{ subtitle }}</p>
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
        <div class="border-b border-slate-800 px-4 py-4">
          <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-current/25 bg-current/5 p-4"
            :class="totalColorClass"
          >
            <div class="min-w-0">
              <p class="flex items-center gap-2 text-xs font-semibold text-slate-200">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" :class="totalColorClass" aria-hidden="true"></span>
                Totaal saldo-effect
              </p>
              <p class="mt-1.5 text-[11px] text-slate-400">
                {{ transactions.length }} onderliggende {{ transactions.length === 1 ? 'mutatie' : 'mutaties' }}
              </p>
            </div>
            <p class="whitespace-nowrap font-mono text-2xl font-bold tabular-nums">
              {{ total >= 0 ? "+" : "−" }}€ {{ euro(Math.abs(total)) }}
            </p>
          </div>
          <div v-if="transactions.length > 0" class="mt-4 flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-xs font-semibold text-slate-300">Onderbouwing</h4>
            <p class="text-[11px] text-slate-400">Selecteer een mutatie voor details</p>
          </div>
        </div>
        <p v-if="transactions.length === 0" class="px-5 py-10 text-center text-sm text-slate-400">
          Geen mutaties voor dit overzicht in deze periode.
        </p>
        <ul v-else class="divide-y divide-slate-800/70">
          <li
            v-for="tx in transactions"
            :key="tx.id"
          >
            <button
              type="button"
              class="group w-full px-5 py-3 flex items-center justify-between gap-3 text-left hover:bg-slate-800/50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-400"
              title="Bekijk alle transactiegegevens"
              @click="openTransactionDetail(tx)"
            >
              <div class="min-w-0 flex-1">
                <TransactionDate :date="tx.date" :time="tx.time" />
                <p class="text-sm text-white truncate">{{ tx.description }}</p>
                <p v-if="tx.counterparty" class="text-[11px] text-slate-400 truncate">
                  {{ tx.counterparty }}
                </p>
              </div>
              <span
                class="font-mono text-sm font-bold shrink-0 tabular-nums whitespace-nowrap"
                :class="tx.amount >= 0 ? 'text-emerald-400' : 'text-rose-400'"
              >
                {{ tx.amount >= 0 ? "+" : "−" }}€ {{ euro(Math.abs(tx.amount)) }}
              </span>
              <ChevronRight class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-colors group-hover:text-slate-200 group-focus-visible:text-slate-200" aria-hidden="true" />
            </button>
          </li>
        </ul>
      </div>

      <div
        class="px-5 py-3 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3 shrink-0"
      >
        <div class="text-xs">
          <span class="text-slate-400">Totaal</span>
          <span class="ml-2 font-mono font-bold tabular-nums whitespace-nowrap" :class="totalColorClass">
            {{ total >= 0 ? "+" : "−" }}€ {{ euro(Math.abs(total)) }}
          </span>
        </div>
        <div class="flex flex-wrap justify-end gap-2">
          <button
            v-if="onOpenInTransactions"
            type="button"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold transition-colors"
            @click="onOpenInTransactions"
          >
            Open in Transacties
          </button>
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
  </ModalShell>
</template>
