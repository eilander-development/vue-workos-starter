<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { X, Check, Repeat, Trash2, ListPlus } from "lucide-vue-next";
import type {
  BudgetItem,
  BudgetCategoryGroup,
  MonthlyBudget,
  CategoryDefinition,
  BudgetType,
  BudgetMonthEntry,
} from "../types";
import ConfirmDialog from "./ConfirmDialog.vue";
import BudgetMonthEntriesModal from "./BudgetMonthEntriesModal.vue";

type FrequencyMode = "current_only" | "all_months" | "quarterly" | "custom" | "monthly_lines";

const props = defineProps<{
  isOpen: boolean;
  onClose: () => void;
  item: BudgetItem | null;
  currentMonthId: string;
  allMonths: MonthlyBudget[];
  categories?: CategoryDefinition[];
  onSave: (
    itemId: string,
    updatedData: {
      name: string;
      group: BudgetCategoryGroup;
      type?: BudgetType;
      monthlyAmounts: Record<string, number>;
      monthlyEntries?: Record<string, BudgetMonthEntry[]>;
    }
  ) => void;
  onDelete?: (itemId: string) => void | Promise<void>;
}>();

function sumEntries(entries: BudgetMonthEntry[]): number {
  return Math.round(entries.reduce((s, e) => s + (Number(e.amount) || 0), 0) * 100) / 100;
}

function itemUsesMonthlyLines(item: BudgetItem | null, allMonths: MonthlyBudget[]): boolean {
  if (!item) return false;
  return allMonths.some((m) => {
    const found = m.items.find((i) => i.id === item.id);
    return (found?.monthEntries?.length ?? 0) > 0;
  });
}

const name = ref("");
const group = ref<BudgetCategoryGroup>("Dagelijks Leven");
const currentAmount = ref(0);
const frequencyMode = ref<FrequencyMode>("current_only");
const monthlyValues = ref<Record<string, number>>({});
const monthlyEntries = ref<Record<string, BudgetMonthEntry[]>>({});
const entriesModalOpen = ref(false);
const confirmDelete = ref(false);
const deleting = ref(false);

const usesLines = computed(() => frequencyMode.value === "monthly_lines");
const currentMonthEntriesTotal = computed(() =>
  sumEntries(monthlyEntries.value[props.currentMonthId] || [])
);

watch(
  () => [props.item, props.isOpen, props.currentMonthId, props.allMonths] as const,
  () => {
    const item = props.item;
    if (!item || !props.isOpen) return;

    name.value = item.name;
    group.value = item.group;
    currentAmount.value = item.actual;
    entriesModalOpen.value = false;

    const initialMap: Record<string, number> = {};
    const initialEntries: Record<string, BudgetMonthEntry[]> = {};

    props.allMonths.forEach((m) => {
      const found = m.items.find((i) => i.id === item.id);
      initialMap[m.monthId] = found ? found.actual : m.monthId === props.currentMonthId ? item.actual : 0;
      const entries = found?.monthEntries?.length
        ? found.monthEntries.map((e) => ({ ...e }))
        : [];
      initialEntries[m.monthId] = entries;
      if (entries.length > 0) {
        initialMap[m.monthId] = sumEntries(entries);
      }
    });

    monthlyValues.value = initialMap;
    monthlyEntries.value = initialEntries;

    if (itemUsesMonthlyLines(item, props.allMonths)) {
      frequencyMode.value = "monthly_lines";
    } else {
      const nonZeroMonths = Object.entries(initialMap).filter(([, v]) => v > 0);
      const isQuarterly =
        nonZeroMonths.length === 4 && ["jan", "apr", "jul", "okt"].every((m) => (initialMap[m] || 0) > 0);
      frequencyMode.value = isQuarterly ? "quarterly" : "current_only";
    }

    confirmDelete.value = false;
    deleting.value = false;
  },
  { immediate: true, deep: true }
);

const groupOptions = computed(() =>
  props.categories && props.categories.length > 0
    ? props.categories.map((c) => c.name)
    : [
        "Inkomsten",
        "Woning",
        "Dagelijks Leven",
        "Vervoersmiddelen",
        "Verzekeringen",
        "Spaargeld",
        "Leningen",
        "Overige Vaste Kosten",
        "Overige Kosten",
      ]
);

function syncAmountsFromEntries() {
  const updated = { ...monthlyValues.value };
  props.allMonths.forEach((m) => {
    updated[m.monthId] = sumEntries(monthlyEntries.value[m.monthId] || []);
  });
  monthlyValues.value = updated;
  currentAmount.value = updated[props.currentMonthId] || 0;
}

function handleEntriesSave(entriesByMonth: Record<string, BudgetMonthEntry[]>) {
  monthlyEntries.value = entriesByMonth;
  syncAmountsFromEntries();
}

function handleFrequencyChange(mode: FrequencyMode) {
  frequencyMode.value = mode;

  if (mode === "monthly_lines") {
    entriesModalOpen.value = true;
    return;
  }

  const updated = { ...monthlyValues.value };
  const item = props.item!;

  if (mode === "all_months") {
    props.allMonths.forEach((m) => {
      updated[m.monthId] = currentAmount.value;
    });
  } else if (mode === "quarterly") {
    props.allMonths.forEach((m) => {
      if (["jan", "apr", "jul", "okt"].includes(m.monthId)) {
        updated[m.monthId] = currentAmount.value > 0 ? currentAmount.value : item.actual || 299.97;
      } else {
        updated[m.monthId] = 0;
      }
    });
  } else if (mode === "current_only") {
    updated[props.currentMonthId] = currentAmount.value;
  }
  monthlyValues.value = updated;
}

function handleCurrentAmountChange(val: number) {
  if (usesLines.value) return;
  currentAmount.value = val;
  const updated = { ...monthlyValues.value };

  if (frequencyMode.value === "current_only") {
    updated[props.currentMonthId] = val;
  } else if (frequencyMode.value === "all_months") {
    props.allMonths.forEach((m) => {
      updated[m.monthId] = val;
    });
  } else if (frequencyMode.value === "quarterly") {
    props.allMonths.forEach((m) => {
      if (["jan", "apr", "jul", "okt"].includes(m.monthId)) {
        updated[m.monthId] = val;
      } else {
        updated[m.monthId] = 0;
      }
    });
  }
  monthlyValues.value = updated;
}

function handleMonthAmountChange(monthId: string, val: number) {
  if (usesLines.value) return;
  monthlyValues.value = { ...monthlyValues.value, [monthId]: val };
  if (monthId === props.currentMonthId) {
    currentAmount.value = val;
  }
  frequencyMode.value = "custom";
}

function buildFinalPayload() {
  const item = props.item!;
  let finalAmounts: Record<string, number> = { ...monthlyValues.value };
  let finalEntries: Record<string, BudgetMonthEntry[]> | undefined;

  if (frequencyMode.value === "monthly_lines") {
    finalEntries = {};
    props.allMonths.forEach((m) => {
      const cleaned = (monthlyEntries.value[m.monthId] || [])
        .map((row) => ({
          ...row,
          description: row.description.trim(),
          amount: Number(row.amount) || 0,
        }))
        .filter((row) => row.description !== "" || Math.abs(row.amount) > 0);
      finalEntries![m.monthId] = cleaned;
      finalAmounts[m.monthId] = sumEntries(cleaned);
    });
  } else {
    finalEntries = {};
    props.allMonths.forEach((m) => {
      finalEntries![m.monthId] = [];
    });

    if (frequencyMode.value === "current_only") {
      finalAmounts[props.currentMonthId] = currentAmount.value;
    } else if (frequencyMode.value === "all_months") {
      props.allMonths.forEach((m) => {
        finalAmounts[m.monthId] = currentAmount.value;
      });
    } else if (frequencyMode.value === "quarterly") {
      props.allMonths.forEach((m) => {
        finalAmounts[m.monthId] = ["jan", "apr", "jul", "okt"].includes(m.monthId)
          ? currentAmount.value
          : 0;
      });
    }
  }

  let matchedType: BudgetType = item.type;
  const foundCat = props.categories?.find((c) => c.name === group.value);
  if (foundCat) {
    matchedType = foundCat.type;
  } else if (group.value === "Inkomsten") {
    matchedType = "inkomsten";
  } else if (group.value === "Spaargeld") {
    matchedType = "sparen";
  } else {
    matchedType = "uitgaven";
  }

  return {
    name: name.value.trim(),
    group: group.value,
    type: matchedType,
    monthlyAmounts: finalAmounts,
    monthlyEntries: finalEntries,
  };
}

function handleSubmit() {
  const item = props.item;
  if (!item || !name.value.trim()) return;
  props.onSave(item.id, buildFinalPayload());
  props.onClose();
}

function handleConfirmDelete() {
  if (!props.onDelete || !props.item || deleting.value) return;
  deleting.value = true;
  Promise.resolve(props.onDelete(props.item.id))
    .then(() => {
      confirmDelete.value = false;
      props.onClose();
    })
    .catch(() => {
      deleting.value = false;
    });
}

const frequencyOptions = [
  ["current_only", "Alleen deze maand", "Enkele aanpassing"],
  ["all_months", "Elke maand", "12x per jaar hetzelfde"],
  ["quarterly", "Per kwartaal (4x)", "Jan, Apr, Jul, Okt"],
  ["custom", "Handmatig / Maatwerk", "Per maand instellen"],
  ["monthly_lines", "Regels per maand", "Omschrijving + bedrag per regel"],
] as const;
</script>

<template>
  <div
    v-if="isOpen && item"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in"
  >
    <div
      class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden"
    >
      <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-850">
        <div>
          <h3 class="font-bold text-white text-base">Begrotingspost Aanpassen</h3>
          <p class="text-xs text-slate-400">
            Stel het budgetbedrag in en bepaal in welke maanden deze post actief is
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

      <form class="p-5 space-y-5 overflow-y-auto" @submit.prevent="handleSubmit">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-slate-300 text-xs font-semibold mb-1">Naam Begrotingspost</label>
            <input
              v-model="name"
              type="text"
              required
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-slate-300 text-xs font-semibold mb-1">Categoriegroep</label>
            <select
              v-model="group"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white text-xs focus:outline-none focus:border-indigo-500"
            >
              <option v-for="g in groupOptions" :key="g" :value="g">{{ g }}</option>
            </select>
          </div>
        </div>

        <div class="bg-slate-950/60 p-4 rounded-xl border border-slate-800 space-y-3">
          <div class="flex items-center justify-between">
            <label class="text-xs font-semibold text-slate-200">Budget Bedrag (€)</label>
            <span class="text-[11px] text-indigo-400 font-mono">
              {{ usesLines ? "Som van regels" : "Live gekoppeld aan bank" }}
            </span>
          </div>
          <div class="relative">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-mono text-sm">€</span>
            <input
              type="number"
              step="0.01"
              inputmode="decimal"
              required
              :value="usesLines ? currentMonthEntriesTotal : currentAmount"
              :readonly="usesLines"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-8 pr-4 py-2 text-white font-mono text-base font-bold focus:outline-none focus:border-indigo-500"
              :class="usesLines ? 'opacity-90 cursor-default' : ''"
              placeholder="0.00"
              @input="
                !usesLines &&
                  handleCurrentAmountChange(parseFloat(($event.target as HTMLInputElement).value) || 0)
              "
            />
          </div>
          <button
            v-if="usesLines"
            type="button"
            class="w-full flex items-center justify-center gap-1.5 border border-indigo-500/40 bg-indigo-600/10 hover:bg-indigo-600/20 text-indigo-200 rounded-xl py-2 text-xs font-medium transition-colors"
            @click="entriesModalOpen = true"
          >
            <ListPlus class="w-3.5 h-3.5" />
            Regels per maand beheren
          </button>
        </div>

        <div class="space-y-2.5">
          <label class="block text-slate-300 text-xs font-semibold flex items-center gap-1.5">
            <Repeat class="w-3.5 h-3.5 text-indigo-400" />
            <span>Uitkeringsschema & Frequentie</span>
          </label>

          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <button
              v-for="[mode, title, subtitle] in frequencyOptions"
              :key="mode"
              type="button"
              class="p-2.5 rounded-xl border text-left text-xs transition-all"
              :class="
                frequencyMode === mode
                  ? 'bg-indigo-600/20 border-indigo-500 text-white font-semibold'
                  : 'bg-slate-800/60 border-slate-700/60 text-slate-300 hover:bg-slate-800'
              "
              @click="handleFrequencyChange(mode)"
            >
              <span class="block font-medium">{{ title }}</span>
              <span class="text-[10px] text-slate-400">{{ subtitle }}</span>
            </button>
          </div>
        </div>

        <div
          v-if="!usesLines"
          class="bg-slate-950/40 p-3.5 rounded-xl border border-slate-800/80"
        >
          <div class="flex items-center justify-between mb-2.5">
            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">
              Begroot bedrag over 2026 per maand
            </span>
            <span class="text-[11px] text-slate-500">
              In maanden met € 0,00 staat de post niet op "In afwachting"
            </span>
          </div>

          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2 font-mono">
            <div
              v-for="m in allMonths"
              :key="m.monthId"
              class="p-2 rounded-lg border text-center transition-all"
              :class="
                m.monthId === currentMonthId
                  ? 'bg-indigo-950/40 border-indigo-500/50'
                  : 'bg-slate-900 border-slate-800'
              "
            >
              <span class="block text-[10px] font-sans font-semibold text-slate-400 mb-1">
                {{ m.monthName.slice(0, 3) }}{{ m.monthId === currentMonthId ? " ★" : "" }}
              </span>
              <input
                type="number"
                step="0.01"
                inputmode="decimal"
                :value="monthlyValues[m.monthId] || 0"
                class="w-full bg-slate-800 border rounded text-center text-xs py-1 px-1 focus:outline-none focus:border-indigo-500"
                :class="
                  (monthlyValues[m.monthId] || 0) === 0
                    ? 'text-slate-500 border-slate-700/40'
                    : 'text-white font-bold border-slate-700'
                "
                @input="
                  handleMonthAmountChange(
                    m.monthId,
                    parseFloat(($event.target as HTMLInputElement).value) || 0
                  )
                "
              />
            </div>
          </div>
        </div>

        <div
          v-else
          class="bg-slate-950/40 p-3.5 rounded-xl border border-indigo-500/20 text-[11px] text-slate-400"
        >
          Bedragen per maand worden berekend uit de regels. Open
          <button
            type="button"
            class="text-indigo-300 hover:text-indigo-200 underline"
            @click="entriesModalOpen = true"
          >
            regels per maand
          </button>
          om omschrijvingen en bedragen te beheren.
        </div>

        <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
          <button
            v-if="onDelete"
            type="button"
            class="flex items-center gap-1.5 text-xs text-rose-400 hover:text-rose-300 p-2 rounded-lg hover:bg-rose-500/10 transition-colors"
            @click="confirmDelete = true"
          >
            <Trash2 class="w-3.5 h-3.5" />
            <span>Post Verwijderen</span>
          </button>
          <div v-else />

          <div class="flex items-center gap-2">
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
              <span>Wijzigingen Opslaan</span>
            </button>
          </div>
        </div>
      </form>
    </div>

    <BudgetMonthEntriesModal
      :is-open="entriesModalOpen"
      :item-name="name || item.name"
      :all-months="allMonths"
      :current-month-id="currentMonthId"
      :entries-by-month="monthlyEntries"
      :on-close="() => (entriesModalOpen = false)"
      :on-save="handleEntriesSave"
    />

    <ConfirmDialog
      :is-open="confirmDelete"
      :title="`“${name || item.name}” verwijderen?`"
      description="Deze begrotingspost verdwijnt uit alle maanden. Gekoppelde mutaties blijven staan, maar raken deze post kwijt."
      confirm-label="Ja, verwijderen"
      :busy="deleting"
      :on-cancel="() => { if (!deleting) confirmDelete = false; }"
      :on-confirm="handleConfirmDelete"
    />
  </div>
</template>
