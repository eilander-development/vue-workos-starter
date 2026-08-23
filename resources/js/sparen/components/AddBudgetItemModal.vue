<script setup lang="ts">
import { ref, watch } from "vue";
import { X } from "lucide-vue-next";
import type { BudgetItem, BudgetCategoryGroup, BudgetType, CategoryDefinition } from "../types";

const props = defineProps<{
  isOpen: boolean;
  onClose: () => void;
  onAdd: (item: Omit<BudgetItem, "id">) => void;
  categories?: CategoryDefinition[];
  defaultGroup?: BudgetCategoryGroup;
}>();

const name = ref("");
const group = ref<BudgetCategoryGroup>(props.defaultGroup || "Dagelijks Leven");
const type = ref<BudgetType>("uitgaven");
const estimated = ref("");
const actual = ref("");
const paidOrReceived = ref("0");

function updateTypeForGroup(selectedGroupName: string) {
  const foundCat = props.categories?.find((c) => c.name === selectedGroupName);
  if (foundCat) {
    type.value = foundCat.type;
  } else if (selectedGroupName === "Inkomsten") {
    type.value = "inkomsten";
  } else if (selectedGroupName === "Spaargeld") {
    type.value = "sparen";
  } else {
    type.value = "uitgaven";
  }
}

watch(
  () => [props.isOpen, props.defaultGroup, props.categories] as const,
  () => {
    if (!props.isOpen) return;
    const initialGrp =
      props.defaultGroup ||
      (props.categories && props.categories.length > 0 ? props.categories[0].name : "Dagelijks Leven");
    group.value = initialGrp;
    updateTypeForGroup(initialGrp);
    name.value = "";
    estimated.value = "";
    actual.value = "";
    paidOrReceived.value = "0";
  }
);

function handleGroupChange(newGroup: BudgetCategoryGroup) {
  group.value = newGroup;
  updateTypeForGroup(newGroup);
}

function handleEstimatedInput(value: string) {
  estimated.value = value;
  if (!actual.value) actual.value = value;
}

function handleSubmit() {
  if (!name.value.trim()) return;

  const est = parseFloat(estimated.value) || 0;
  const act = parseFloat(actual.value) || est;
  const paid = parseFloat(paidOrReceived.value) || 0;

  props.onAdd({
    name: name.value.trim(),
    group: group.value,
    type: type.value,
    estimated: est,
    actual: act,
    paidOrReceived: paid,
    isPaid: paid >= act && act > 0,
  });

  props.onClose();
}

const categoryOptions =
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
      ];
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-white text-base">Nieuwe Begrotingspost Toevoegen</h3>
        <button type="button" class="text-slate-400 hover:text-white p-1 rounded-lg" @click="onClose">
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-5 space-y-4 text-xs" @submit.prevent="handleSubmit">
        <div>
          <label class="block text-slate-300 font-semibold mb-1">Naam Begrotingspost</label>
          <input
            v-model="name"
            type="text"
            required
            placeholder="Bijv. Netflix, Schoolboeken of Cadeaus"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:border-indigo-500"
          />
        </div>

        <div>
          <label class="block text-slate-300 font-semibold mb-1">Rubriek / Categoriegroep</label>
          <select
            :value="group"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white text-sm focus:outline-none focus:border-indigo-500 cursor-pointer"
            @change="handleGroupChange(($event.target as HTMLSelectElement).value)"
          >
            <option v-for="g in categoryOptions" :key="g" :value="g">{{ g }}</option>
          </select>
        </div>

        <div class="bg-slate-950/40 p-3 rounded-xl border border-slate-800">
          <span class="text-[11px] text-slate-400 block mb-1">Type van deze post:</span>
          <span
            class="text-xs font-bold font-mono px-2 py-0.5 rounded"
            :class="
              type === 'inkomsten'
                ? 'bg-emerald-950 text-emerald-400'
                : type === 'sparen'
                  ? 'bg-indigo-950 text-indigo-400'
                  : 'bg-rose-950 text-rose-400'
            "
          >
            {{ type.toUpperCase() }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Budget Bedrag (€)</label>
            <input
              type="number"
              step="0.01"
              required
              placeholder="0.00"
              :value="estimated"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-400 focus:outline-none focus:border-indigo-500"
              @input="handleEstimatedInput(($event.target as HTMLInputElement).value)"
            />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Werkelijk Bedrag (€)</label>
            <input
              v-model="actual"
              type="number"
              step="0.01"
              placeholder="0.00"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>

        <div class="pt-3 border-t border-slate-800 flex justify-end gap-2">
          <button
            type="button"
            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium transition-colors"
            @click="onClose"
          >
            Annuleren
          </button>
          <button
            type="submit"
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl transition-colors shadow-md shadow-emerald-600/20"
          >
            Toevoegen aan Begroting
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
