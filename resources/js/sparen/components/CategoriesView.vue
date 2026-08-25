<script setup lang="ts">
import { computed, ref, type Component } from "vue";
import {
  Tags,
  TrendingUp,
  Building2,
  Car,
  ShoppingBag,
  Receipt,
  ShieldCheck,
  PiggyBank,
  Plus,
  Edit2,
  ArrowDownCircle,
  FolderPlus,
  HelpCircle,
  Sparkles,
  ArrowLeftRight,
} from "lucide-vue-next";
import type {
  MonthlyBudget,
  BudgetItem,
  CategoryDefinition,
  BudgetType,
  BudgetCategoryGroup,
} from "../types";

const props = defineProps<{
  currentMonth: MonthlyBudget;
  categories: CategoryDefinition[];
  onOpenAddBudgetItem: (defaultGroup?: BudgetCategoryGroup) => void;
  onOpenEditBudgetItem?: (item: BudgetItem) => void;
  onOpenAddCategory: () => void;
  onOpenEditCategory: (cat: CategoryDefinition) => void;
  onDeleteCategory: (catId: string) => void;
  onUpdateBudgetItem: (itemId: string, updates: Partial<BudgetItem>) => void;
  onDeleteBudgetItem?: (itemId: string) => void;
}>();

const selectedTypeFilter = ref<"ALL" | BudgetType>("ALL");
const searchTerm = ref("");
const showExplanation = ref(false);

function getCategoryIcon(name: string, type: BudgetType): Component {
  const lower = name.toLowerCase();
  if (type === "inkomsten" || lower.includes("inkomst") || lower.includes("salaris")) return TrendingUp;
  if (type === "sparen" || lower.includes("spaar") || lower.includes("buffer")) return PiggyBank;
  if (lower.includes("woning") || lower.includes("huis") || lower.includes("huur")) return Building2;
  if (lower.includes("vervoer") || lower.includes("auto") || lower.includes("benzine")) return Car;
  if (lower.includes("dagelijks") || lower.includes("boodschappen") || lower.includes("winkel"))
    return ShoppingBag;
  if (lower.includes("verzekering") || lower.includes("zorg")) return ShieldCheck;
  if (lower.includes("lening") || lower.includes("schuld") || lower.includes("aflossing")) return Receipt;
  return Tags;
}

function getColorClasses(colorName?: string) {
  switch (colorName) {
    case "emerald":
      return {
        bg: "bg-emerald-500/10",
        border: "border-emerald-500/30",
        text: "text-emerald-400",
      };
    case "blue":
      return { bg: "bg-blue-500/10", border: "border-blue-500/30", text: "text-blue-400" };
    case "cyan":
      return { bg: "bg-cyan-500/10", border: "border-cyan-500/30", text: "text-cyan-400" };
    case "purple":
      return { bg: "bg-purple-500/10", border: "border-purple-500/30", text: "text-purple-400" };
    case "amber":
      return { bg: "bg-amber-500/10", border: "border-amber-500/30", text: "text-amber-400" };
    case "pink":
      return { bg: "bg-pink-500/10", border: "border-pink-500/30", text: "text-pink-400" };
    case "rose":
      return { bg: "bg-rose-500/10", border: "border-rose-500/30", text: "text-rose-400" };
    case "indigo":
    default:
      return { bg: "bg-indigo-500/10", border: "border-indigo-500/30", text: "text-indigo-400" };
  }
}

const extraCategories = computed((): CategoryDefinition[] =>
  [...new Set(props.currentMonth.items.map((i) => i.group).filter(Boolean))]
    .filter((group) => !props.categories.some((c) => c.name.toLowerCase() === group.toLowerCase()))
    .map((group) => {
      const sample = props.currentMonth.items.find((i) => i.group === group)!;
      return {
        id: `derived-${group}`,
        name: group,
        type: sample.type,
        description: "Komt uit de maandbegroting",
        isDefault: false,
      };
    })
);

const allCategories = computed(() => [...props.categories, ...extraCategories.value]);

const filteredCategories = computed(() =>
  allCategories.value.filter((cat) => {
    const matchesType = selectedTypeFilter.value === "ALL" || cat.type === selectedTypeFilter.value;
    const matchesSearch =
      cat.name.toLowerCase().includes(searchTerm.value.toLowerCase()) ||
      (cat.description && cat.description.toLowerCase().includes(searchTerm.value.toLowerCase()));
    return matchesType && matchesSearch;
  })
);

const incomeCategories = computed(() => allCategories.value.filter((c) => c.type === "inkomsten"));
const expenseCategories = computed(() => allCategories.value.filter((c) => c.type === "uitgaven"));
const savingsCategories = computed(() => allCategories.value.filter((c) => c.type === "sparen"));

function itemsFor(cat: CategoryDefinition) {
  return props.currentMonth.items.filter((i) => i.group.toLowerCase() === cat.name.toLowerCase());
}

function totalActual(cat: CategoryDefinition) {
  return itemsFor(cat).reduce((s, i) => s + (i.actual ?? i.estimated ?? 0), 0);
}

function moveTargets(cat: CategoryDefinition) {
  return allCategories.value.filter((c) => c.type === cat.type);
}

function moveItem(item: BudgetItem, groupName: string) {
  if (groupName === item.group) {
    return;
  }
  const target = allCategories.value.find((c) => c.name === groupName);
  props.onUpdateBudgetItem(item.id, {
    group: groupName as BudgetCategoryGroup,
    type: target?.type ?? item.type,
  });
}
</script>

<template>
  <div id="categories-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-4 sm:p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2.5">
          <div
            class="w-9 h-9 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400"
          >
            <Tags class="w-5 h-5" />
          </div>
          <div>
            <h2 class="text-xl font-bold text-white tracking-tight">Categorieën & Rubrieken Beheer</h2>
            <p class="text-xs text-slate-400 mt-0.5">
              Bepaal zelf welke categorieën horen bij Inkomsten, Uitgaven of Sparen en beheer onderliggende
              posten
            </p>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 px-3 py-2 rounded-xl text-xs font-medium border border-slate-700 transition-colors"
          @click="showExplanation = !showExplanation"
        >
          <HelpCircle class="w-4 h-4 text-indigo-400" />
          <span>Hoe werkt dit?</span>
        </button>

        <button
          type="button"
          class="flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-md shadow-indigo-600/20 transition-all active:scale-95"
          @click="onOpenAddCategory"
        >
          <FolderPlus class="w-4 h-4" />
          <span>Nieuwe Categorie</span>
        </button>

        <button
          type="button"
          class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-md shadow-emerald-600/20 transition-all active:scale-95"
          @click="onOpenAddBudgetItem()"
        >
          <Plus class="w-4 h-4" />
          <span>Nieuwe Post</span>
        </button>
      </div>
    </div>

    <div
      v-if="showExplanation"
      class="bg-indigo-950/40 border border-indigo-500/30 p-4 rounded-2xl text-xs text-slate-300 space-y-2 animate-fade-in"
    >
      <div class="flex items-center gap-2 text-white font-bold">
        <Sparkles class="w-4 h-4 text-indigo-400" />
        <span>Aanpasbare Categorieën & Begrotingsposten</span>
      </div>
      <p>
        Elke hoofdcategorie is toegewezen aan een
        <strong class="text-white">Type (Inkomsten, Uitgaven of Sparen)</strong>. Je kunt op elk moment een
        categorie toevoegen, hernoemen of het type wijzigen:
      </p>
      <ul class="list-disc list-inside space-y-1 text-slate-300 pl-1">
        <li>
          <strong class="text-emerald-400">Inkomsten rubrieken:</strong> Worden getoond in het
          Inkomstenoverzicht (zoals Salaris, Toeslagen, Teruggaven).
        </li>
        <li>
          <strong class="text-rose-400">Uitgaven rubrieken:</strong> Worden gegroepeerd in de
          Uitgavenoverzichten (zoals Woning, Verzekeringen, Dagelijks Leven).
        </li>
        <li>
          <strong class="text-indigo-400">Sparen rubrieken:</strong> Gekoppeld aan je spaardoelen en
          bufferreserveringen.
        </li>
      </ul>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <button
        type="button"
        class="p-3.5 rounded-2xl border text-left transition-all"
        :class="
          selectedTypeFilter === 'ALL'
            ? 'bg-slate-800 border-indigo-500 shadow-md text-white'
            : 'bg-[#101726] border-slate-800 text-slate-400 hover:border-slate-700'
        "
        @click="selectedTypeFilter = 'ALL'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold">Alle Rubrieken</span>
          <Tags class="w-4 h-4 text-indigo-400" />
        </div>
        <div class="text-lg font-bold font-mono mt-1 text-white">{{ allCategories.length }}</div>
        <span class="text-[10px] text-slate-500">Totaal actief</span>
      </button>

      <button
        type="button"
        class="p-3.5 rounded-2xl border text-left transition-all"
        :class="
          selectedTypeFilter === 'uitgaven'
            ? 'bg-rose-950/40 border-rose-500 shadow-md text-white'
            : 'bg-[#101726] border-slate-800 text-slate-400 hover:border-slate-700'
        "
        @click="selectedTypeFilter = 'uitgaven'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold">Uitgaven</span>
          <ArrowDownCircle class="w-4 h-4 text-rose-400" />
        </div>
        <div class="text-lg font-bold font-mono mt-1 text-rose-400">{{ expenseCategories.length }}</div>
        <span class="text-[10px] text-slate-500">Kostenrubrieken</span>
      </button>

      <button
        type="button"
        class="p-3.5 rounded-2xl border text-left transition-all"
        :class="
          selectedTypeFilter === 'inkomsten'
            ? 'bg-emerald-950/40 border-emerald-500 shadow-md text-white'
            : 'bg-[#101726] border-slate-800 text-slate-400 hover:border-slate-700'
        "
        @click="selectedTypeFilter = 'inkomsten'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold">Inkomsten</span>
          <TrendingUp class="w-4 h-4 text-emerald-400" />
        </div>
        <div class="text-lg font-bold font-mono mt-1 text-emerald-400">{{ incomeCategories.length }}</div>
        <span class="text-[10px] text-slate-500">Inkomstenbronnen</span>
      </button>

      <button
        type="button"
        class="p-3.5 rounded-2xl border text-left transition-all"
        :class="
          selectedTypeFilter === 'sparen'
            ? 'bg-indigo-950/40 border-indigo-500 shadow-md text-white'
            : 'bg-[#101726] border-slate-800 text-slate-400 hover:border-slate-700'
        "
        @click="selectedTypeFilter = 'sparen'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-semibold">Sparen & Buffer</span>
          <PiggyBank class="w-4 h-4 text-indigo-400" />
        </div>
        <div class="text-lg font-bold font-mono mt-1 text-indigo-400">{{ savingsCategories.length }}</div>
        <span class="text-[10px] text-slate-500">Spaarpotten</span>
      </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="cat in filteredCategories"
        :key="cat.id || cat.name"
        class="bg-[#101726] border border-slate-800 hover:border-slate-700/80 rounded-2xl p-5 shadow-sm flex flex-col justify-between transition-all"
      >
        <div>
          <div class="flex items-start justify-between mb-3.5">
            <div class="flex items-center gap-3">
              <div
                class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border"
                :class="[getColorClasses(cat.color).bg, getColorClasses(cat.color).border, getColorClasses(cat.color).text]"
              >
                <component :is="getCategoryIcon(cat.name, cat.type)" class="w-5 h-5" />
              </div>
              <div>
                <h3 class="font-bold text-white text-base leading-snug">{{ cat.name }}</h3>
                <div class="flex items-center gap-1.5 mt-0.5">
                  <span
                    class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded-full border"
                    :class="
                      cat.type === 'inkomsten'
                        ? 'bg-emerald-950/70 text-emerald-400 border-emerald-800/70'
                        : cat.type === 'sparen'
                          ? 'bg-indigo-950/70 text-indigo-400 border-indigo-800/70'
                          : 'bg-rose-950/70 text-rose-400 border-rose-800/70'
                    "
                  >
                    {{ cat.type }}
                  </span>
                  <span class="text-[11px] text-slate-500 font-mono">
                    {{ itemsFor(cat).length }} post{{ itemsFor(cat).length !== 1 ? "en" : "" }}
                  </span>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-1">
              <button
                v-if="!String(cat.id).startsWith('derived-')"
                type="button"
                class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-800 transition-colors"
                title="Categorie bewerken (naam, type, kleur)"
                @click="onOpenEditCategory(cat)"
              >
                <Edit2 class="w-4 h-4" />
              </button>
            </div>
          </div>

          <p v-if="cat.description" class="text-xs text-slate-400 mb-3 line-clamp-2 leading-relaxed">
            {{ cat.description }}
          </p>

          <div class="space-y-1.5 mt-3 max-h-56 overflow-y-auto pr-1">
            <div
              v-if="itemsFor(cat).length === 0"
              class="p-3 bg-slate-900/60 rounded-xl border border-slate-800/60 text-center text-xs text-slate-500"
            >
              Nog geen posten in deze categorie
            </div>
            <div
              v-for="item in itemsFor(cat)"
              :key="item.id"
              class="p-2.5 bg-slate-900/80 hover:bg-slate-850 border border-slate-800/80 hover:border-slate-700 rounded-xl flex items-center justify-between gap-2 text-xs transition-all group"
            >
              <button
                type="button"
                class="flex items-center gap-2 truncate min-w-0 text-left flex-1"
                title="Klik om deze post aan te passen"
                @click="onOpenEditBudgetItem?.(item)"
              >
                <span
                  class="w-1.5 h-1.5 rounded-full bg-slate-600 group-hover:bg-indigo-400 transition-colors shrink-0"
                />
                <span class="font-medium text-slate-200 group-hover:text-white truncate">{{ item.name }}</span>
              </button>

              <div class="flex items-center gap-1.5 shrink-0">
                <label
                  v-if="moveTargets(cat).length > 1"
                  class="relative flex items-center"
                  title="Verplaats naar andere rubriek"
                >
                  <ArrowLeftRight class="w-3 h-3 text-slate-500 pointer-events-none absolute left-1.5" />
                  <select
                    :value="item.group"
                    class="max-w-[7.5rem] bg-slate-800 border border-slate-700 hover:border-indigo-500 text-[10px] text-slate-300 rounded-lg pl-6 pr-1 py-1 focus:outline-none focus:border-indigo-500 cursor-pointer"
                    @click.stop
                    @change="moveItem(item, ($event.target as HTMLSelectElement).value)"
                  >
                    <option v-for="target in moveTargets(cat)" :key="target.id || target.name" :value="target.name">
                      {{ target.name }}
                    </option>
                  </select>
                </label>
                <span class="font-bold text-white text-xs font-mono whitespace-nowrap">
                  € {{ item.actual.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="mt-4 pt-3.5 border-t border-slate-800 flex items-center justify-between text-xs font-mono">
          <div>
            <span class="text-slate-400 font-sans block text-[11px]">
              {{ currentMonth.monthName }} Begroting:
            </span>
            <span class="font-bold text-white text-sm">
              € {{ totalActual(cat).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>

          <button
            type="button"
            class="flex items-center gap-1 text-xs text-indigo-400 hover:text-indigo-300 font-sans font-semibold hover:bg-indigo-600/10 px-2.5 py-1.5 rounded-lg transition-colors"
            @click="onOpenAddBudgetItem(cat.name)"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Post toevoegen</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
