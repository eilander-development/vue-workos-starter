<script setup lang="ts">
import { ref } from "vue";
import { X } from "lucide-vue-next";
import type { Transaction, BudgetCategoryGroup } from "../types";

const props = defineProps<{
  isOpen: boolean;
  onClose: () => void;
  onAdd: (tx: Omit<Transaction, "id">) => void;
}>();

const description = ref("");
const amount = ref("");
const type = ref<"Uitgave" | "Inkomsten" | "Sparen">("Uitgave");
const categoryGroup = ref<BudgetCategoryGroup>("Dagelijks Leven");
const date = ref(new Date().toISOString().split("T")[0]);
const counterparty = ref("");

const categories: BudgetCategoryGroup[] = [
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

function handleSubmit() {
  const numAmount = parseFloat(amount.value);
  if (isNaN(numAmount) || numAmount === 0 || !description.value.trim()) return;

  const finalAmount = type.value === "Uitgave" ? -Math.abs(numAmount) : Math.abs(numAmount);

  props.onAdd({
    date: date.value,
    time: new Date().toLocaleTimeString("nl-NL", { hour: "2-digit", minute: "2-digit" }),
    description: description.value.trim(),
    amount: finalAmount,
    type: type.value,
    categoryGroup: categoryGroup.value,
    accountIban: "NL83INGB0004565868",
    counterparty: counterparty.value.trim() || undefined,
    source: "Handmatig",
  });

  props.onClose();
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm animate-in fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between">
        <h3 class="font-bold text-white text-base">Nieuwe Transactie Toevoegen</h3>
        <button type="button" class="text-slate-400 hover:text-white p-1 rounded-lg" @click="onClose">
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-5 space-y-4 text-xs" @submit.prevent="handleSubmit">
        <div>
          <label class="block text-slate-300 font-semibold mb-1">Omschrijving / Bedrijf</label>
          <input
            v-model="description"
            type="text"
            required
            placeholder="Bijv. AH 8732 Apeldoorn of Salaris"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Type Transactie</label>
            <select
              v-model="type"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option value="Uitgave">Uitgave (Afschrijving)</option>
              <option value="Inkomsten">Inkomsten (Bijschrijving)</option>
              <option value="Sparen">Sparen (Naar buffer)</option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Bedrag (€)</label>
            <input
              v-model="amount"
              type="number"
              step="0.01"
              required
              placeholder="0.00"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Categorie</label>
            <select
              v-model="categoryGroup"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
            >
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Datum</label>
            <input
              v-model="date"
              type="date"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
            />
          </div>
        </div>

        <div>
          <label class="block text-slate-300 font-semibold mb-1">Tegenpartij (Optioneel)</label>
          <input
            v-model="counterparty"
            type="text"
            placeholder="Bijv. Albert Heijn, GreenChoice, etc."
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
          />
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
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl transition-colors shadow-md shadow-indigo-600/20"
          >
            Transactie Opslaan
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
