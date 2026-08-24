<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { X, Check, Plus, Trash2 } from "lucide-vue-next";
import type { BudgetMonthEntry, MonthlyBudget } from "../types";

const props = defineProps<{
  isOpen: boolean;
  itemName: string;
  allMonths: MonthlyBudget[];
  currentMonthId: string;
  entriesByMonth: Record<string, BudgetMonthEntry[]>;
  onClose: () => void;
  onSave: (entriesByMonth: Record<string, BudgetMonthEntry[]>) => void;
}>();

function newEntry(): BudgetMonthEntry {
  return {
    id: `entry-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    description: "",
    amount: 0,
  };
}

function sumEntries(entries: BudgetMonthEntry[]): number {
  return Math.round(entries.reduce((s, e) => s + (Number(e.amount) || 0), 0) * 100) / 100;
}

const activeEntryMonth = ref(props.currentMonthId);
const monthlyEntries = ref<Record<string, BudgetMonthEntry[]>>({});
const amountDrafts = ref<Record<string, string>>({});

function amountKey(row: BudgetMonthEntry): string {
  return `${activeEntryMonth.value}:${row.id}`;
}

function parseDecimalInput(raw: string): number {
  const normalized = raw.trim().replace(/\s/g, "").replace(",", ".");
  if (!normalized || normalized === "." || normalized === "-") return 0;
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? parsed : 0;
}

function displayAmount(row: BudgetMonthEntry): string {
  const key = amountKey(row);
  if (key in amountDrafts.value) return amountDrafts.value[key];
  if (row.amount === 0) return "";
  return String(row.amount).replace(".", ",");
}

function handleAmountInput(index: number, row: BudgetMonthEntry, raw: string) {
  amountDrafts.value = { ...amountDrafts.value, [amountKey(row)]: raw };
  updateEntryAmount(index, parseDecimalInput(raw));
}

function handleAmountBlur(row: BudgetMonthEntry) {
  const key = amountKey(row);
  if (!(key in amountDrafts.value)) return;
  const next = { ...amountDrafts.value };
  delete next[key];
  amountDrafts.value = next;
}

function selectEntryMonth(monthId: string) {
  activeEntryMonth.value = monthId;
  amountDrafts.value = {};
}

watch(
  () => [props.isOpen, props.entriesByMonth, props.currentMonthId] as const,
  () => {
    if (!props.isOpen) return;
    activeEntryMonth.value = props.currentMonthId;
    amountDrafts.value = {};
    const copy: Record<string, BudgetMonthEntry[]> = {};
    props.allMonths.forEach((m) => {
      copy[m.monthId] = (props.entriesByMonth[m.monthId] || []).map((e) => ({ ...e }));
    });
    monthlyEntries.value = copy;
  },
  { immediate: true, deep: true }
);

const activeEntries = computed(() => monthlyEntries.value[activeEntryMonth.value] || []);
const activeEntriesTotal = computed(() => sumEntries(activeEntries.value));

function syncAmountFromEntries(monthId: string, entries: BudgetMonthEntry[]) {
  monthlyEntries.value = { ...monthlyEntries.value, [monthId]: entries };
}

function updateActiveEntries(next: BudgetMonthEntry[]) {
  syncAmountFromEntries(activeEntryMonth.value, next);
}

function updateEntryDescription(index: number, description: string) {
  updateActiveEntries(
    activeEntries.value.map((entry, i) => (i === index ? { ...entry, description } : entry))
  );
}

function updateEntryAmount(index: number, amount: number) {
  updateActiveEntries(
    activeEntries.value.map((entry, i) => (i === index ? { ...entry, amount } : entry))
  );
}

function handleSubmit() {
  const cleaned: Record<string, BudgetMonthEntry[]> = {};
  props.allMonths.forEach((m) => {
    cleaned[m.monthId] = (monthlyEntries.value[m.monthId] || [])
      .map((row) => {
        const draft = amountDrafts.value[`${m.monthId}:${row.id}`];
        const amount =
          draft !== undefined ? parseDecimalInput(draft) : parseDecimalInput(String(row.amount));
        return {
          ...row,
          description: row.description.trim(),
          amount,
        };
      })
      .filter((row) => row.description !== "" || Math.abs(row.amount) > 0);
  });
  props.onSave(cleaned);
  props.onClose();
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in"
  >
    <div
      class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden"
    >
      <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-850">
        <div>
          <h3 class="font-bold text-white text-base">Regels per maand</h3>
          <p class="text-xs text-slate-400">
            Voeg per maand losse regels toe (omschrijving + bedrag). De som is het begrote bedrag voor die
            maand.
          </p>
        </div>
        <button
          type="button"
          class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
          @click="onClose"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-5 space-y-4 overflow-y-auto" @submit.prevent="handleSubmit">
        <p class="text-xs text-slate-300">
          Post: <span class="font-semibold text-white">{{ itemName }}</span>
        </p>

        <div class="space-y-3">
          <div class="flex items-center justify-between gap-2">
            <label class="text-xs font-semibold text-slate-200">Regels per maand</label>
            <span class="text-[11px] font-mono text-indigo-300">
              Totaal {{ allMonths.find((m) => m.monthId === activeEntryMonth)?.monthName }}: €
              {{ activeEntriesTotal.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>

          <div class="flex flex-wrap gap-1.5">
            <button
              v-for="m in allMonths"
              :key="m.monthId"
              type="button"
              class="px-2.5 py-1.5 rounded-lg text-[11px] border transition-colors"
              :class="
                m.monthId === activeEntryMonth
                  ? 'bg-indigo-600/30 border-indigo-500 text-white font-semibold'
                  : sumEntries(monthlyEntries[m.monthId] || []) > 0
                    ? 'bg-slate-800 border-slate-600 text-slate-200'
                    : 'bg-slate-900 border-slate-800 text-slate-500'
              "
              @click="selectEntryMonth(m.monthId)"
            >
              {{ m.monthName.slice(0, 3)
              }}{{
                sumEntries(monthlyEntries[m.monthId] || []) > 0
                  ? ` · €${Math.round(sumEntries(monthlyEntries[m.monthId] || []))}`
                  : ""
              }}
            </button>
          </div>

          <div class="bg-slate-950/60 border border-slate-800 rounded-xl p-3 space-y-2">
            <p v-if="activeEntries.length === 0" class="text-[11px] text-slate-500 px-1 py-2">
              Nog geen regels. Voeg bijv. “Coolblue restant” of “Tandarts” toe.
            </p>

            <div
              v-for="(row, index) in activeEntries"
              :key="row.id"
              class="grid grid-cols-12 gap-2 items-center"
            >
              <input
                type="text"
                :value="row.description"
                placeholder="Omschrijving"
                class="col-span-7 bg-slate-800 border border-slate-700 rounded-lg px-2.5 py-2 text-xs text-white focus:outline-none focus:border-indigo-500"
                @input="updateEntryDescription(index, ($event.target as HTMLInputElement).value)"
              />
              <div class="col-span-4 relative">
                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-500 text-[11px]">€</span>
                <input
                  type="text"
                  inputmode="decimal"
                  autocomplete="off"
                  :value="displayAmount(row)"
                  class="w-full bg-slate-800 border border-slate-700 rounded-lg pl-5 pr-2 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                  placeholder="0,00"
                  @input="handleAmountInput(index, row, ($event.target as HTMLInputElement).value)"
                  @blur="handleAmountBlur(row)"
                />
              </div>
              <button
                type="button"
                class="col-span-1 p-2 text-rose-400 hover:text-rose-300 hover:bg-rose-500/10 rounded-lg"
                title="Regel verwijderen"
                @click="updateActiveEntries(activeEntries.filter((_, i) => i !== index))"
              >
                <Trash2 class="w-3.5 h-3.5 mx-auto" />
              </button>
            </div>

            <button
              type="button"
              class="w-full mt-1 flex items-center justify-center gap-1.5 border border-dashed border-slate-700 hover:border-indigo-500/60 text-slate-300 hover:text-white rounded-xl py-2 text-xs transition-colors"
              @click="updateActiveEntries([...activeEntries, newEntry()])"
            >
              <Plus class="w-3.5 h-3.5" />
              Regel toevoegen
            </button>
          </div>

          <p class="text-[11px] text-slate-500">
            Koppel bankmutaties handmatig aan deze post. Het verschil tussen begroot (som van regels) en
            gekoppelde betalingen blijft in “nog te betalen” / verwacht eind.
          </p>
        </div>

        <div class="pt-3 border-t border-slate-800 flex items-center justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 rounded-xl text-xs font-medium text-slate-300 hover:bg-slate-800 transition-colors"
            @click="onClose"
          >
            Annuleren
          </button>
          <button
            type="submit"
            class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-5 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
          >
            <Check class="w-4 h-4" />
            <span>Regels toepassen</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
