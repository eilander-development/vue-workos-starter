<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { Plus, Split, Trash2, X } from "lucide-vue-next";
import type { BudgetItem, BudgetType, Rule } from "../types";
import { normalizeAllocations, roundMoney } from "../allocations";

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    onClose: () => void;
    onSave: (rule: Omit<Rule, "matchedCount">) => void;
    editingRule?: Rule | null;
    budgetItems: BudgetItem[];
  }>(),
  {
    editingRule: null,
  }
);

const name = ref("");
const keyword = ref("");
const matchField = ref<"description" | "counterparty" | "both">("description");
const isActive = ref(true);
const rows = ref<{ budgetItemId: string; amount: number }[]>([]);

watch(
  () => [props.isOpen, props.editingRule] as const,
  () => {
    if (!props.isOpen) {
      return;
    }

    const editing = props.editingRule;
    if (editing) {
      name.value = editing.name;
      keyword.value = editing.keyword;
      matchField.value = editing.matchField;
      isActive.value = editing.isActive;
      const existing = normalizeAllocations(editing.allocations);
      rows.value = existing
        ? existing.map((row) => ({ budgetItemId: row.budgetItemId, amount: row.amount }))
        : editing.targetBudgetItemId
          ? [{ budgetItemId: editing.targetBudgetItemId, amount: amountForItem(editing.targetBudgetItemId) }]
          : [];
      return;
    }

    name.value = "";
    keyword.value = "";
    matchField.value = "description";
    isActive.value = true;
    rows.value = [];
  }
);

const unusedItems = computed(() => {
  const used = new Set(rows.value.map((row) => row.budgetItemId));
  return props.budgetItems.filter((item) => !used.has(item.id));
});

const plannedTotal = computed(() =>
  roundMoney(rows.value.reduce((sum, row) => sum + (Number(row.amount) || 0), 0))
);

const canSave = computed(
  () =>
    name.value.trim().length > 0 &&
    keyword.value.trim().length >= 2 &&
    rows.value.length >= 2 &&
    rows.value.every((row) => row.budgetItemId && Number(row.amount) > 0)
);

function amountForItem(itemId: string): number {
  const item = props.budgetItems.find((entry) => entry.id === itemId);
  return roundMoney(Math.abs(item?.actual ?? 0));
}

function itemName(itemId: string): string {
  return props.budgetItems.find((item) => item.id === itemId)?.name ?? itemId;
}

function itemGroup(itemId: string): string {
  return props.budgetItems.find((item) => item.id === itemId)?.group ?? "";
}

function addEnvelope(itemId: string) {
  if (!itemId) {
    return;
  }
  rows.value = [...rows.value, { budgetItemId: itemId, amount: amountForItem(itemId) }];
}

function removeRow(index: number) {
  rows.value = rows.value.filter((_, i) => i !== index);
}

function typeFromItem(item: BudgetItem | undefined): BudgetType {
  return item?.type ?? "uitgaven";
}

function handleSubmit() {
  if (!canSave.value) {
    return;
  }

  const first = props.budgetItems.find((item) => item.id === rows.value[0].budgetItemId);
  props.onSave({
    id: props.editingRule?.id ?? `rule-${Date.now()}`,
    name: name.value.trim(),
    keyword: keyword.value.trim(),
    matchField: matchField.value,
    targetGroup: first?.group ?? "Overige Kosten",
    targetBudgetItemId: rows.value[0].budgetItemId,
    targetType: typeFromItem(first),
    allocations: rows.value.map((row) => ({
      budgetItemId: row.budgetItemId,
      amount: roundMoney(Number(row.amount) || 0),
    })),
    isActive: isActive.value,
  });
  props.onClose();
}

function euro(amount: number): string {
  return amount.toLocaleString("nl-NL", { minimumFractionDigits: 2 });
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-sm"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
      <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2">
          <Split class="w-4 h-4 text-indigo-400" />
          <h3 class="font-bold text-white text-base">
            {{ editingRule ? "Split bewerken" : "Nieuwe split" }}
          </h3>
        </div>
        <button type="button" class="text-slate-400 hover:text-white p-1 rounded-lg" @click="onClose">
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="flex-1 min-h-0 flex flex-col" @submit.prevent="handleSubmit">
        <div class="flex-1 overflow-y-auto p-5 space-y-4 text-xs">
          <p class="text-slate-400">
            Kies de enveloppen die één bankmutatie samen vullen. Het trefwoord herkent die mutaties.
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Naam</label>
              <input
                v-model="name"
                type="text"
                required
                placeholder="Bijv. InShared"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
              />
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Trefwoord</label>
              <input
                v-model="keyword"
                type="text"
                required
                placeholder="Bijv. InShared"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-400 focus:outline-none focus:border-indigo-500"
              />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Zoek in</label>
              <select
                v-model="matchField"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                <option value="description">Omschrijving</option>
                <option value="counterparty">Tegenpartij</option>
                <option value="both">Beide</option>
              </select>
            </div>
            <label class="flex items-end gap-2 text-slate-300 pb-2 cursor-pointer">
              <input v-model="isActive" type="checkbox" class="w-4 h-4 accent-indigo-600 rounded" />
              <span class="font-semibold">{{ isActive ? "Actief" : "Gepauzeerd" }}</span>
            </label>
          </div>

          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <h4 class="text-sm font-bold text-white">Enveloppen</h4>
              <span class="font-mono text-slate-300">
                Samen € {{ euro(plannedTotal) }}
              </span>
            </div>

            <div v-if="rows.length === 0" class="text-slate-500 border border-dashed border-slate-800 rounded-xl p-4 text-center">
              Voeg minstens twee enveloppen toe.
            </div>

            <div
              v-for="(row, index) in rows"
              :key="`${row.budgetItemId}-${index}`"
              class="grid grid-cols-[1fr_7rem_auto] gap-2 items-center bg-slate-800/60 border border-slate-700/70 rounded-xl px-3 py-2"
            >
              <div class="min-w-0">
                <p class="text-white font-semibold truncate">{{ itemName(row.budgetItemId) }}</p>
                <p class="text-[10px] text-slate-500 truncate">{{ itemGroup(row.budgetItemId) }}</p>
              </div>
              <input
                v-model.number="row.amount"
                type="number"
                min="0.01"
                step="0.01"
                class="w-full bg-slate-900 border border-slate-600 rounded-lg px-2 py-1.5 text-white font-mono text-right focus:outline-none focus:border-indigo-500"
              />
              <button
                type="button"
                class="p-1.5 text-slate-400 hover:text-rose-400 rounded-lg"
                title="Envelop verwijderen"
                @click="removeRow(index)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>

            <select
              v-if="unusedItems.length > 0"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
              @change="
                addEnvelope(($event.target as HTMLSelectElement).value);
                ($event.target as HTMLSelectElement).value = '';
              "
            >
              <option value="">+ Envelop toevoegen</option>
              <option v-for="item in unusedItems" :key="item.id" :value="item.id">
                {{ item.group }} · {{ item.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="px-5 py-3 border-t border-slate-800 shrink-0 flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium"
            @click="onClose"
          >
            Annuleren
          </button>
          <button
            type="submit"
            :disabled="!canSave"
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl flex items-center gap-1.5"
          >
            <Plus class="w-4 h-4" />
            {{ editingRule ? "Split opslaan" : "Split aanmaken" }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
