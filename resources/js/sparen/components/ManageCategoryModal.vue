<script setup lang="ts">
import { ref, watch } from "vue";
import { X, Check, Trash2, Tag, TrendingUp, ArrowDownCircle, PiggyBank } from "lucide-vue-next";
import type { CategoryDefinition, BudgetType } from "../types";

const props = defineProps<{
  isOpen: boolean;
  onClose: () => void;
  category: CategoryDefinition | null;
  onSave: (cat: CategoryDefinition) => void;
  onDelete?: (catId: string) => void;
}>();

const name = ref("");
const type = ref<BudgetType>("uitgaven");
const color = ref("indigo");
const description = ref("");

watch(
  () => [props.category, props.isOpen] as const,
  () => {
    if (!props.isOpen) return;
    if (props.category) {
      name.value = props.category.name;
      type.value = props.category.type;
      color.value = props.category.color || "indigo";
      description.value = props.category.description || "";
    } else {
      name.value = "";
      type.value = "uitgaven";
      color.value = "indigo";
      description.value = "";
    }
  },
  { immediate: true }
);

const colors = [
  { name: "indigo", bg: "bg-indigo-500", border: "border-indigo-400" },
  { name: "emerald", bg: "bg-emerald-500", border: "border-emerald-400" },
  { name: "blue", bg: "bg-blue-500", border: "border-blue-400" },
  { name: "cyan", bg: "bg-cyan-500", border: "border-cyan-400" },
  { name: "purple", bg: "bg-purple-500", border: "border-purple-400" },
  { name: "amber", bg: "bg-amber-500", border: "border-amber-400" },
  { name: "pink", bg: "bg-pink-500", border: "border-pink-400" },
  { name: "rose", bg: "bg-rose-500", border: "border-rose-400" },
];

function handleSubmit() {
  if (!name.value.trim()) return;
  props.onSave({
    id: props.category ? props.category.id : `cat-${Date.now()}`,
    name: name.value.trim(),
    type: type.value,
    color: color.value,
    description: description.value.trim(),
    isDefault: props.category?.isDefault,
  });
  props.onClose();
}

function handleDelete() {
  if (!props.category || !props.onDelete) return;
  if (confirm(`Weet je zeker dat je de categorie "${props.category.name}" wilt verwijderen?`)) {
    props.onDelete(props.category.id);
    props.onClose();
  }
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full shadow-2xl overflow-hidden flex flex-col">
      <div class="p-4 sm:p-5 border-b border-slate-800 flex items-center justify-between bg-slate-850">
        <div class="flex items-center gap-2.5">
          <div class="w-8 h-8 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400">
            <Tag class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">
              {{ category ? "Categoriegroep Bewerken" : "Nieuwe Categoriegroep" }}
            </h3>
            <p class="text-[11px] text-slate-400">
              Stel de naam en het type (inkomsten / uitgaven / sparen) in
            </p>
          </div>
        </div>
        <button
          type="button"
          class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
          @click="onClose"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-4 sm:p-5 space-y-4" @submit.prevent="handleSubmit">
        <div>
          <label class="block text-slate-300 text-xs font-semibold mb-1">Naam Categoriegroep / Rubriek</label>
          <input
            v-model="name"
            type="text"
            required
            placeholder="Bijv. Vrije Tijd & Hobby of Kinderen"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-white text-sm focus:outline-none focus:border-indigo-500 placeholder-slate-500"
          />
        </div>

        <div>
          <label class="block text-slate-300 text-xs font-semibold mb-1.5">Type Begrotingsrubriek</label>
          <div class="grid grid-cols-3 gap-2">
            <button
              type="button"
              class="p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all"
              :class="
                type === 'uitgaven'
                  ? 'bg-rose-950/40 border-rose-500 text-rose-300 font-bold'
                  : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:bg-slate-800'
              "
              @click="type = 'uitgaven'"
            >
              <ArrowDownCircle
                class="w-4 h-4"
                :class="type === 'uitgaven' ? 'text-rose-400' : 'text-slate-500'"
              />
              <span class="text-xs">Uitgaven</span>
            </button>
            <button
              type="button"
              class="p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all"
              :class="
                type === 'inkomsten'
                  ? 'bg-emerald-950/40 border-emerald-500 text-emerald-300 font-bold'
                  : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:bg-slate-800'
              "
              @click="type = 'inkomsten'"
            >
              <TrendingUp
                class="w-4 h-4"
                :class="type === 'inkomsten' ? 'text-emerald-400' : 'text-slate-500'"
              />
              <span class="text-xs">Inkomsten</span>
            </button>
            <button
              type="button"
              class="p-3 rounded-xl border flex flex-col items-center gap-1.5 transition-all"
              :class="
                type === 'sparen'
                  ? 'bg-indigo-950/40 border-indigo-500 text-indigo-300 font-bold'
                  : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:bg-slate-800'
              "
              @click="type = 'sparen'"
            >
              <PiggyBank
                class="w-4 h-4"
                :class="type === 'sparen' ? 'text-indigo-400' : 'text-slate-500'"
              />
              <span class="text-xs">Sparen</span>
            </button>
          </div>
          <p class="text-[11px] text-slate-500 mt-1.5">
            <template v-if="type === 'inkomsten'">Posten in deze categorie tellen mee als inkomende geldstromen.</template>
            <template v-else-if="type === 'uitgaven'">Posten in deze categorie worden verwerkt als maandelijkse kosten/vaste lasten.</template>
            <template v-else>Posten in deze categorie worden verwerkt als overboeking naar spaardoelen/buffer.</template>
          </p>
        </div>

        <div>
          <label class="block text-slate-300 text-xs font-semibold mb-1.5">Kleuraccent</label>
          <div class="flex items-center gap-2.5">
            <button
              v-for="c in colors"
              :key="c.name"
              type="button"
              class="w-7 h-7 rounded-full transition-all flex items-center justify-center"
              :class="[
                c.bg,
                color === c.name
                  ? 'ring-2 ring-white ring-offset-2 ring-offset-slate-900 scale-110'
                  : 'opacity-70 hover:opacity-100',
              ]"
              @click="color = c.name"
            >
              <Check v-if="color === c.name" class="w-3.5 h-3.5 text-white stroke-[3]" />
            </button>
          </div>
        </div>

        <div>
          <label class="block text-slate-300 text-xs font-semibold mb-1">
            Toelichting / Omschrijving (Optioneel)
          </label>
          <input
            v-model="description"
            type="text"
            placeholder="Bijv. Abonnementen, uitstapjes en cadeaus"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white text-xs focus:outline-none focus:border-indigo-500 placeholder-slate-500"
          />
        </div>

        <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
          <button
            v-if="category && onDelete"
            type="button"
            class="flex items-center gap-1.5 text-xs text-rose-400 hover:text-rose-300 p-2 rounded-lg hover:bg-rose-500/10 transition-colors"
            @click="handleDelete"
          >
            <Trash2 class="w-3.5 h-3.5" />
            <span>Categorie Verwijderen</span>
          </button>
          <div v-else />

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="px-3.5 py-2 rounded-xl text-xs font-medium text-slate-300 hover:bg-slate-800 transition-colors"
              @click="onClose"
            >
              Annuleren
            </button>
            <button
              type="submit"
              class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
            >
              <Check class="w-4 h-4" />
              <span>{{ category ? "Opslaan" : "Categorie Aanmaken" }}</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
