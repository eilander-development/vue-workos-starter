<script setup lang="ts">
import { computed, ref } from "vue";
import { X, PiggyBank } from "lucide-vue-next";
import type { MonthlyBudget, SavingsGoal, Transaction } from "../types";
import type { PotSettlement } from "../potSettlement";
import TransactionDate from "./TransactionDate.vue";

const props = defineProps<{
  isOpen: boolean;
  onClose: () => void;
  goal: SavingsGoal | null;
  settlement: PotSettlement | null;
  currentMonth: MonthlyBudget;
}>();

const activeTab = ref<"spent" | "compensated">("spent");
const searchTerm = ref("");

const transactions = computed(() => {
  if (!props.settlement) return [];
  const list =
    activeTab.value === "spent"
      ? props.settlement.spentTransactions
      : props.settlement.compensationTransactions;

  if (!searchTerm.value) return list;

  const term = searchTerm.value.toLowerCase();
  return list.filter(
    (tx) =>
      tx.description.toLowerCase().includes(term) ||
      (tx.counterparty && tx.counterparty.toLowerCase().includes(term)) ||
      tx.date.includes(term)
  );
});

function close() {
  searchTerm.value = "";
  activeTab.value = "spent";
  props.onClose();
}

function budgetItemLabel(tx: Transaction): string | null {
  if (!props.settlement || !tx.budgetItemId) return null;
  const item = props.settlement.budgetItems.find((b) => b.id === tx.budgetItemId);
  return item ? `${item.group} › ${item.name}` : null;
}
</script>

<template>
  <div
    v-if="isOpen && goal && settlement"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-in fade-in"
  >
    <div
      class="bg-slate-900 border border-slate-800 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]"
    >
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-3 min-w-0">
          <div class="p-2.5 rounded-xl border bg-amber-500/15 border-amber-500/30 text-amber-400 shrink-0">
            <PiggyBank class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <h3 class="font-bold text-white text-base truncate">{{ goal.name }}</h3>
              <span
                class="text-[9px] bg-amber-950 text-amber-300 border border-amber-800 px-1.5 py-0.5 rounded font-semibold uppercase shrink-0"
              >
                Potje
              </span>
            </div>
            <p
              v-if="settlement.budgetItems.length > 0"
              class="text-xs text-slate-400 mt-0.5 truncate"
            >
              Verrekening voor
              {{
                settlement.budgetItems.length === 1
                  ? `${settlement.budgetItems[0].group} › ${settlement.budgetItems[0].name}`
                  : settlement.budgetItems.map((item) => item.name).join(", ")
              }}
            </p>
          </div>
        </div>
        <button
          type="button"
          class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors shrink-0"
          @click="close"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <div class="p-4 sm:p-5 border-b border-slate-800 bg-slate-950/40 space-y-3 shrink-0">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">Begroot / in pot</span>
            <span class="text-sm font-bold text-white font-mono mt-0.5 block">
              € {{ settlement.budgeted.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">Uitgegeven (bank)</span>
            <span class="text-sm font-bold text-rose-400 font-mono mt-0.5 block">
              € {{ settlement.spent.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="bg-slate-900/80 border border-slate-800 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium line-through">Al gecompenseerd</span>
            <span class="text-sm font-bold text-slate-500 line-through font-mono mt-0.5 block">
              € {{ settlement.compensated.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
          <div class="bg-slate-900/80 border border-amber-900/50 p-3 rounded-xl">
            <span class="text-slate-400 text-[11px] block font-medium">Nog over te zetten</span>
            <span class="text-sm font-bold text-yellow-400 font-mono mt-0.5 block">
              € {{ settlement.toTransfer.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pt-1">
          <div class="flex items-center gap-1 bg-slate-900 p-1 rounded-xl border border-slate-800 w-fit">
            <button
              type="button"
              class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
              :class="
                activeTab === 'spent'
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'text-slate-400 hover:text-white'
              "
              @click="activeTab = 'spent'"
            >
              Uitgaven ({{ settlement.spentTransactions.length }})
            </button>
            <button
              type="button"
              class="px-3 py-1 rounded-lg text-xs font-semibold transition-all"
              :class="
                activeTab === 'compensated'
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'text-slate-400 hover:text-white'
              "
              @click="activeTab = 'compensated'"
            >
              Compensatie ({{ settlement.compensationTransactions.length }})
            </button>
          </div>
          <div class="relative flex-1 max-w-xs">
            <input
              v-model="searchTerm"
              type="text"
              placeholder="Zoek in mutaties..."
              class="w-full bg-slate-900 border border-slate-800 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>
      </div>

      <div class="p-4 sm:p-5 overflow-y-auto flex-1">
        <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-2">
          {{ activeTab === "spent" ? "Uitgaven op betaalrekening" : "Overboekingen pot → rekening" }}
          ({{ transactions.length }})
        </h4>

        <div
          v-if="transactions.length === 0"
          class="text-center py-10 border border-dashed border-slate-800 rounded-2xl bg-slate-950/20 p-6"
        >
          <p class="text-sm text-slate-400">Geen mutaties in {{ currentMonth.monthName }} {{ currentMonth.year }}</p>
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="tx in transactions"
            :key="tx.id"
            class="bg-slate-800/60 border border-slate-700/60 p-3 rounded-xl flex items-center justify-between gap-3 text-xs"
          >
            <div class="min-w-0 space-y-0.5">
              <div class="flex items-center gap-2">
                <TransactionDate :date="tx.date" :time="tx.time" size="sm" />
                <span class="text-white font-medium truncate">{{ tx.description }}</span>
              </div>
              <p
                v-if="activeTab === 'spent' && budgetItemLabel(tx)"
                class="text-[10px] text-amber-300/80 truncate"
              >
                {{ budgetItemLabel(tx) }}
              </p>
              <p v-else-if="tx.counterparty" class="text-[10px] text-slate-400 truncate">
                {{ tx.counterparty }}
              </p>
            </div>
            <span
              class="font-mono font-bold shrink-0"
              :class="activeTab === 'spent' ? 'text-rose-400' : 'text-emerald-400'"
            >
              € {{ Math.abs(tx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
        </div>
      </div>

      <div class="px-5 py-3 border-t border-slate-800 bg-slate-850 shrink-0 flex justify-end">
        <button
          type="button"
          class="px-4 py-2 rounded-xl text-sm font-semibold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition-colors"
          @click="close"
        >
          Sluiten
        </button>
      </div>
    </div>
  </div>
</template>
