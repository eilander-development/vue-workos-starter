<script setup lang="ts">
import { computed, onUnmounted, watch } from "vue";
import { X, Receipt } from "lucide-vue-next";
import type { BudgetItem, Rule, SavingsGoal, Transaction } from "../types";
import TransactionDate from "./TransactionDate.vue";
import { normalizeAllocations } from "../allocations";

const props = withDefaults(
  defineProps<{
    transaction: Transaction | null;
    budgetItems?: BudgetItem[];
    rules?: Rule[];
    savingsGoals?: SavingsGoal[];
    onClose: () => void;
  }>(),
  {
    budgetItems: () => [],
    rules: () => [],
    savingsGoals: () => [],
  }
);

const isOpen = computed(() => props.transaction !== null);

const euro = (n: number) =>
  n.toLocaleString("nl-NL", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const budgetItemMap = computed(() => {
  const map = new Map<string, BudgetItem>();
  props.budgetItems.forEach((item) => map.set(item.id, item));
  return map;
});

const matchedRule = computed(() => {
  const id = props.transaction?.matchedRuleId;
  if (!id) return null;
  return props.rules.find((rule) => rule.id === id) ?? null;
});

const savingsGoal = computed(() => {
  const id = props.transaction?.assignedSavingsGoalId;
  if (!id) return null;
  return props.savingsGoals.find((goal) => goal.id === id) ?? null;
});

const allocations = computed(() =>
  props.transaction ? normalizeAllocations(props.transaction.allocations) ?? [] : []
);

const bankRecord = computed(() => asRecord(props.transaction?.bankPayload));

const bankDates = computed(() => {
  const payload = bankRecord.value;
  if (!payload) return [];

  const nested = asRecord(payload.raw) ?? payload;
  const rows: Array<{ label: string; value: string }> = [];
  const add = (label: string, keys: string[]) => {
    const value = stringField(nested, keys) ?? stringField(payload, keys);
    if (value) rows.push({ label, value });
  };

  add("Boekdatum (bank)", ["booking_date", "bookingDate"]);
  add("Boekdatum + tijd", ["booking_datetime", "booking_date_time", "bookingDateTime"]);
  add("Valutadatum", ["value_date", "valueDate"]);
  add("Transactiedatum", ["transaction_date", "transactionDate"]);
  return rows;
});

const rawJson = computed(() => {
  if (!props.transaction?.bankPayload) return null;
  try {
    return JSON.stringify(props.transaction.bankPayload, null, 2);
  } catch {
    return null;
  }
});

function asRecord(value: unknown): Record<string, unknown> | null {
  if (value && typeof value === "object" && !Array.isArray(value)) {
    return value as Record<string, unknown>;
  }
  return null;
}

function stringField(record: Record<string, unknown> | null, keys: string[]): string | null {
  if (!record) return null;
  for (const key of keys) {
    const value = record[key];
    if (typeof value === "string" && value.trim() !== "") {
      return value.trim();
    }
  }
  return null;
}

function onKeydown(event: KeyboardEvent) {
  if (event.key === "Escape" && isOpen.value) {
    event.stopPropagation();
    props.onClose();
  }
}

watch(
  isOpen,
  (open) => {
    if (open) {
      window.addEventListener("keydown", onKeydown, true);
    } else {
      window.removeEventListener("keydown", onKeydown, true);
    }
  },
  { immediate: true }
);

onUnmounted(() => {
  window.removeEventListener("keydown", onKeydown, true);
});
</script>

<template>
  <div
    v-if="transaction"
    class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/75 backdrop-blur-sm"
    @click="onClose"
  >
    <div
      class="bg-slate-900 border border-slate-800 w-full sm:max-w-2xl sm:rounded-2xl rounded-t-2xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col"
      @click.stop
    >
      <div class="px-5 py-4 border-b border-slate-800 flex items-start justify-between gap-3 shrink-0">
        <div class="flex items-start gap-3 min-w-0">
          <div class="p-2 rounded-xl bg-indigo-500/15 text-indigo-400 border border-indigo-500/30 shrink-0">
            <Receipt class="w-4 h-4" />
          </div>
          <div class="min-w-0">
            <h3 class="font-bold text-white text-base">Transactie</h3>
            <p class="text-xs text-slate-400 mt-0.5 break-words">{{ transaction.description }}</p>
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

      <div class="overflow-y-auto flex-1 p-5 space-y-5 text-xs">
        <section class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
          <div class="bg-slate-800/60 border border-slate-700/70 rounded-xl p-3">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Boekdatum</p>
            <div class="mt-1">
              <TransactionDate :date="transaction.date" :time="transaction.time" />
            </div>
          </div>
          <div class="bg-slate-800/60 border border-slate-700/70 rounded-xl p-3">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Bedrag</p>
            <p
              class="mt-1 font-mono font-bold text-sm"
              :class="transaction.amount >= 0 ? 'text-emerald-400' : 'text-rose-400'"
            >
              {{ transaction.amount >= 0 ? "+" : "−" }}€ {{ euro(Math.abs(transaction.amount)) }}
            </p>
          </div>
          <div class="bg-slate-800/60 border border-slate-700/70 rounded-xl p-3">
            <p class="text-[10px] uppercase tracking-wider text-slate-500 font-semibold">Type</p>
            <p class="mt-1 text-slate-200 font-semibold">{{ transaction.type }}</p>
          </div>
        </section>

        <section class="space-y-2">
          <h4 class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Bankmutatie</h4>
          <dl class="bg-slate-800/40 border border-slate-800 rounded-xl divide-y divide-slate-800">
            <div class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Omschrijving</dt>
              <dd class="text-slate-200 text-right break-words">{{ transaction.description }}</dd>
            </div>
            <div v-if="transaction.counterparty" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Tegenpartij</dt>
              <dd class="text-slate-200 text-right break-words">{{ transaction.counterparty }}</dd>
            </div>
            <div v-if="transaction.counterpartyIban" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Tegenpartij IBAN</dt>
              <dd class="text-slate-200 font-mono text-right break-all">{{ transaction.counterpartyIban }}</dd>
            </div>
            <div v-if="transaction.accountIban" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Eigen rekening</dt>
              <dd class="text-slate-200 font-mono text-right break-all">{{ transaction.accountIban }}</dd>
            </div>
            <div class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Bron</dt>
              <dd class="text-slate-200">{{ transaction.source }}</dd>
            </div>
            <div v-if="transaction.isPending" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Status</dt>
              <dd class="text-amber-300">In behandeling</dd>
            </div>
            <div v-if="transaction.importedAt" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Geïmporteerd</dt>
              <dd class="text-slate-200 font-mono">{{ transaction.importedAt }}</dd>
            </div>
            <div class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">ID</dt>
              <dd class="text-slate-400 font-mono text-right break-all">{{ transaction.id }}</dd>
            </div>
          </dl>
        </section>

        <section v-if="bankDates.length > 0" class="space-y-2">
          <h4 class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">
            Datums van de bank
          </h4>
          <dl class="bg-slate-800/40 border border-slate-800 rounded-xl divide-y divide-slate-800">
            <div v-for="row in bankDates" :key="row.label" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">{{ row.label }}</dt>
              <dd class="text-slate-200 font-mono text-right">{{ row.value }}</dd>
            </div>
          </dl>
          <p class="text-[10px] text-slate-500">
            De app gebruikt overal de boekdatum. Factuurdatums in de omschrijving tellen niet mee.
          </p>
        </section>

        <section class="space-y-2">
          <h4 class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Koppeling</h4>
          <dl class="bg-slate-800/40 border border-slate-800 rounded-xl divide-y divide-slate-800">
            <div class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Rubriek</dt>
              <dd class="text-slate-200 text-right">{{ transaction.categoryGroup }}</dd>
            </div>
            <div v-if="allocations.length > 0">
              <div
                v-for="row in allocations"
                :key="row.budgetItemId"
                class="px-3.5 py-2.5 flex justify-between gap-3"
              >
                <dt class="text-slate-500 shrink-0">
                  {{ allocations.length > 1 ? "Deelpost" : "Begrotingspost" }}
                </dt>
                <dd class="text-slate-200 text-right">
                  {{ budgetItemMap.get(row.budgetItemId)?.name ?? row.budgetItemId }}
                  <span v-if="allocations.length > 1" class="font-mono text-slate-400 ml-1">
                    € {{ euro(row.amount) }}
                  </span>
                </dd>
              </div>
            </div>
            <div v-else-if="transaction.budgetItemId" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Begrotingspost</dt>
              <dd class="text-slate-200 text-right">
                {{ budgetItemMap.get(transaction.budgetItemId)?.name ?? transaction.budgetItemId }}
              </dd>
            </div>
            <div v-if="matchedRule" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Koppelregel</dt>
              <dd class="text-slate-200 text-right">{{ matchedRule.name }}</dd>
            </div>
            <div v-if="savingsGoal" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Spaardoel</dt>
              <dd class="text-slate-200 text-right">{{ savingsGoal.name }}</dd>
            </div>
            <div v-if="transaction.linkExcluded" class="px-3.5 py-2.5 flex justify-between gap-3">
              <dt class="text-slate-500 shrink-0">Koppelen</dt>
              <dd class="text-slate-200 text-right">
                Niet koppelen
                <span v-if="transaction.linkExclusionReason" class="block text-slate-500 mt-0.5">
                  {{ transaction.linkExclusionReason }}
                </span>
              </dd>
            </div>
          </dl>
        </section>

        <section v-if="rawJson" class="space-y-2">
          <h4 class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">
            Ruwe bankdata
          </h4>
          <pre
            class="bg-slate-950 border border-slate-800 rounded-xl p-3 text-[10px] text-slate-400 overflow-x-auto max-h-72 font-mono whitespace-pre-wrap break-all"
          >{{ rawJson }}</pre>
        </section>
      </div>

      <div class="px-5 py-3 border-t border-slate-800 flex justify-end shrink-0">
        <button
          type="button"
          class="text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-white px-3.5 py-1.5 rounded-lg"
          @click="onClose"
        >
          Sluiten
        </button>
      </div>
    </div>
  </div>
</template>
