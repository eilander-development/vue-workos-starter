<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { X, Check, Repeat, Trash2, Plus } from "lucide-vue-next";
import type {
  BudgetItem,
  BudgetCategoryGroup,
  MonthlyBudget,
  CategoryDefinition,
  BudgetType,
  BudgetMonthEntry,
} from "../types";
import ConfirmDialog from "./ConfirmDialog.vue";

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

function isOpenstaandItem(item: BudgetItem | null): boolean {
  if (!item) return false;
  return item.name.trim().toLowerCase() === "openstaand" || item.id === "ovk-4";
}

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

const openstaand = computed(() => isOpenstaandItem(props.item));
const name = ref("");
const group = ref<BudgetCategoryGroup>("Dagelijks Leven");
const currentAmount = ref(0);
const frequencyMode = ref<"current_only" | "all_months" | "quarterly" | "custom">("current_only");
const monthlyValues = ref<Record<string, number>>({});
const monthlyEntries = ref<Record<string, BudgetMonthEntry[]>>({});
const activeEntryMonth = ref(props.currentMonthId);
const confirmDelete = ref(false);
const deleting = ref(false);

watch(
  () => [props.item, props.isOpen, props.currentMonthId, props.allMonths, openstaand.value] as const,
  () => {
    const item = props.item;
    if (!item || !props.isOpen) return;

    name.value = item.name;
    group.value = item.group;
    currentAmount.value = item.actual;
    activeEntryMonth.value = props.currentMonthId;

    const initialMap: Record<string, number> = {};
    const initialEntries: Record<string, BudgetMonthEntry[]> = {};

    props.allMonths.forEach((m) => {
      const found = m.items.find((i) => i.id === item.id);
      initialMap[m.monthId] = found ? found.actual : m.monthId === props.currentMonthId ? item.actual : 0;
      const entries = found?.monthEntries?.length
        ? found.monthEntries.map((e) => ({ ...e }))
        : found && found.actual > 0 && isOpenstaandItem(item)
          ? [{ id: newEntry().id, description: found.name || "Openstaand", amount: found.actual }]
          : [];
      initialEntries[m.monthId] = entries;
      if (entries.length > 0) {
        initialMap[m.monthId] = sumEntries(entries);
      }
    });

    monthlyValues.value = initialMap;
    monthlyEntries.value = initialEntries;

    if (openstaand.value) {
      frequencyMode.value = "custom";
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

const activeEntries = computed(() => monthlyEntries.value[activeEntryMonth.value] || []);
const activeEntriesTotal = computed(() => sumEntries(activeEntries.value));

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

function syncAmountFromEntries(monthId: string, entries: BudgetMonthEntry[]) {
  const total = sumEntries(entries);
  monthlyValues.value = { ...monthlyValues.value, [monthId]: total };
  if (monthId === props.currentMonthId) {
    currentAmount.value = total;
  }
}

function updateActiveEntries(next: BudgetMonthEntry[]) {
  monthlyEntries.value = { ...monthlyEntries.value, [activeEntryMonth.value]: next };
  syncAmountFromEntries(activeEntryMonth.value, next);
}

function handleFrequencyChange(mode: "current_only" | "all_months" | "quarterly" | "custom") {
  frequencyMode.value = mode;
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
  monthlyValues.value = { ...monthlyValues.value, [monthId]: val };
  if (monthId === props.currentMonthId) {
    currentAmount.value = val;
  }
  frequencyMode.value = "custom";
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
  const item = props.item;
  if (!item || !name.value.trim()) return;

  let finalAmounts: Record<string, number> = { ...monthlyValues.value };
  let finalEntries: Record<string, BudgetMonthEntry[]> | undefined;

  if (openstaand.value) {
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
  } else if (frequencyMode.value === "current_only") {
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

  props.onSave(item.id, {
    name: name.value.trim(),
    group: group.value,
    type: matchedType,
    monthlyAmounts: finalAmounts,
    monthlyEntries: finalEntries,
  });

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
  ["quarterly", "Per kwartaal (4x)", "Jan, Apr, Jul, Okt (Kinderbijslag)"],
  ["custom", "Handmatig / Maatwerk", "Per maand instellen"],
] as const;
</script>

<template>
  <div
    v-if="isOpen && item"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden">
      <div class="p-5 border-b border-slate-800 flex items-center justify-between bg-slate-850">
        <div>
          <h3 class="font-bold text-white text-base">
            {{ openstaand ? "Openstaande posten beheren" : "Begrotingspost Aanpassen" }}
          </h3>
          <p class="text-xs text-slate-400">
            {{
              openstaand
                ? "Voeg per maand losse regels toe (omschrijving + bedrag). Die tellen mee in nog te betalen en verwacht eind."
                : "Stel het budgetbedrag in en bepaal in welke maanden deze post actief is"
            }}
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

        <div v-if="openstaand" class="space-y-3">
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
              @click="activeEntryMonth = m.monthId"
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
                  type="number"
                  step="0.01"
                  :value="row.amount"
                  class="w-full bg-slate-800 border border-slate-700 rounded-lg pl-5 pr-2 py-2 text-xs text-white font-mono focus:outline-none focus:border-indigo-500"
                  @input="
                    updateEntryAmount(index, parseFloat(($event.target as HTMLInputElement).value) || 0)
                  "
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
            Na opslaan koppel je bankmutaties handmatig aan de post Openstaand (via de post openen). Het
            verschil tussen begroot (som van regels) en gekoppelde betalingen blijft in “nog te betalen” /
            verwacht eind.
          </p>
        </div>

        <template v-else>
          <div class="bg-slate-950/60 p-4 rounded-xl border border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
              <label class="text-xs font-semibold text-slate-200">Budget Bedrag (€)</label>
              <span class="text-[11px] text-indigo-400 font-mono">Live gekoppeld aan bank</span>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 font-mono text-sm">€</span>
              <input
                type="number"
                step="0.01"
                required
                :value="currentAmount"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl pl-8 pr-4 py-2 text-white font-mono text-base font-bold focus:outline-none focus:border-indigo-500"
                placeholder="0.00"
                @input="
                  handleCurrentAmountChange(parseFloat(($event.target as HTMLInputElement).value) || 0)
                "
              />
            </div>
          </div>

          <div class="space-y-2.5">
            <label class="block text-slate-300 text-xs font-semibold flex items-center gap-1.5">
              <Repeat class="w-3.5 h-3.5 text-indigo-400" />
              <span>Uitkeringsschema & Frequentie</span>
            </label>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
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

          <div class="bg-slate-950/40 p-3.5 rounded-xl border border-slate-800/80">
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
        </template>

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
