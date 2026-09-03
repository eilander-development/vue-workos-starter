<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { X, Sliders, Search } from "lucide-vue-next";
import type {
  Rule,
  BudgetCategoryGroup,
  BudgetType,
  BudgetItem,
  CategoryDefinition,
  Transaction,
  MonthlyBudget,
} from "../types";
import {
  matchingUnlinkedTransactions,
  transactionsMatchingRule,
} from "../matchRule";
import {
  formatReportingPeriodLabel,
  isTransactionInReportingMonth,
  reportingPeriodForMonth,
} from "../month";
import TransactionDate from "./TransactionDate.vue";

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    onClose: () => void;
    onSave: (rule: Omit<Rule, "matchedCount">) => void;
    editingRule?: Rule | null;
    initialKeyword?: string;
    initialGroup?: BudgetCategoryGroup;
    initialBudgetItemId?: string;
    budgetItems?: BudgetItem[];
    categories?: CategoryDefinition[];
    transactions?: Transaction[];
    currentMonth?: MonthlyBudget | null;
    allMonths?: MonthlyBudget[];
  }>(),
  {
    editingRule: null,
    initialKeyword: "",
    initialGroup: "Dagelijks Leven",
    initialBudgetItemId: "",
    budgetItems: () => [],
    categories: () => [],
    transactions: () => [],
    currentMonth: null,
    allMonths: () => [],
  }
);

const name = ref("");
const keyword = ref("");
const targetGroup = ref<BudgetCategoryGroup>("Dagelijks Leven");
const targetBudgetItemId = ref("");
const targetType = ref<BudgetType>("uitgaven");
const matchField = ref<"description" | "counterparty" | "both">("description");
const isActive = ref(true);
const periodKey = ref("current");
const searchTerm = ref("");

const isEditing = computed(() => !!props.editingRule);

watch(
  () => [props.isOpen, props.editingRule, props.initialKeyword, props.initialGroup, props.initialBudgetItemId] as const,
  () => {
    if (!props.isOpen) return;

    const editing = props.editingRule;
    if (editing) {
      name.value = editing.name;
      keyword.value = editing.keyword;
      targetGroup.value = editing.targetGroup;
      targetBudgetItemId.value = editing.targetBudgetItemId ?? "";
      targetType.value = editing.targetType;
      matchField.value = editing.matchField;
      isActive.value = editing.isActive;
    } else {
      name.value = props.initialKeyword ? `Regel: ${props.initialKeyword}` : "";
      keyword.value = props.initialKeyword;
      targetGroup.value = props.initialGroup;
      targetBudgetItemId.value = props.initialBudgetItemId;
      matchField.value = "description";
      isActive.value = true;
      applyTypeFromGroup(props.initialGroup);
    }

    periodKey.value = "current";
    searchTerm.value = "";
  }
);

function applyTypeFromGroup(grp: BudgetCategoryGroup) {
  const catMatch = props.categories.find((c) => c.name === grp);
  if (catMatch) {
    targetType.value = catMatch.type;
  } else if (grp === "Inkomsten") {
    targetType.value = "inkomsten";
  } else if (grp === "Spaargeld") {
    targetType.value = "sparen";
  } else {
    targetType.value = "uitgaven";
  }
}

const draftRule = computed(() => ({
  keyword: keyword.value.trim(),
  matchField: matchField.value,
  targetType: targetType.value,
}));

const extraMatches = computed(() =>
  matchingUnlinkedTransactions(
    props.transactions,
    draftRule.value.keyword,
    draftRule.value.matchField,
    undefined,
    draftRule.value.targetType
  )
);

const allKeywordMatches = computed(() =>
  transactionsMatchingRule(props.transactions, draftRule.value, { ignoreDirection: true })
);

const selectedPeriod = computed(() => {
  if (periodKey.value === "all") return null;
  if (periodKey.value === "current") return props.currentMonth;
  return props.allMonths.find((month) => `${month.monthId}-${month.year}` === periodKey.value) ?? props.currentMonth;
});

const visibleMatches = computed(() => {
  const term = searchTerm.value.trim().toLowerCase();
  return allKeywordMatches.value.filter((tx) => {
    if (selectedPeriod.value && !isTransactionInReportingMonth(tx, selectedPeriod.value)) {
      return false;
    }
    if (!term) return true;
    return (
      tx.description.toLowerCase().includes(term) ||
      (tx.counterparty?.toLowerCase().includes(term) ?? false)
    );
  });
});

const periodOptions = computed(() => {
  const current = props.currentMonth;
  const options: { key: string; label: string }[] = [{ key: "all", label: "Alle periodes" }];
  if (current) {
    options.push({
      key: "current",
      label: `${current.monthName} ${current.year} (${formatReportingPeriodLabel(reportingPeriodForMonth(current))})`,
    });
  }
  for (const month of props.allMonths) {
    const key = `${month.monthId}-${month.year}`;
    if (current && month.monthId === current.monthId && month.year === current.year) {
      continue;
    }
    options.push({
      key,
      label: `${month.monthName} ${month.year}`,
    });
  }
  return options;
});

const availableGroups = computed(() =>
  props.categories.length > 0
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

const itemsInSelectedGroup = computed(() =>
  props.budgetItems.filter((i) => i.group === targetGroup.value)
);

function handleGroupChange(grp: BudgetCategoryGroup) {
  targetGroup.value = grp;
  applyTypeFromGroup(grp);
  const itemsInGrp = props.budgetItems.filter((i) => i.group === grp);
  if (!itemsInGrp.some((item) => item.id === targetBudgetItemId.value)) {
    targetBudgetItemId.value = itemsInGrp.length > 0 ? itemsInGrp[0].id : "";
  }
}

function handleSubmit() {
  if (!name.value.trim() || !keyword.value.trim()) return;

  props.onSave({
    id: props.editingRule?.id ?? `rule-${Date.now()}`,
    name: name.value.trim(),
    keyword: keyword.value.trim(),
    matchField: matchField.value,
    targetGroup: targetGroup.value,
    targetBudgetItemId: targetBudgetItemId.value || undefined,
    targetType: targetType.value,
    isActive: isActive.value,
  });

  props.onClose();
}

function euro(amount: number) {
  return Math.abs(amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 });
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-sm animate-in fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-2xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2">
          <Sliders class="w-4 h-4 text-indigo-400" />
          <h3 class="font-bold text-white text-base">
            {{ isEditing ? "Koppelregel bewerken" : "Nieuwe koppelregel" }}
          </h3>
        </div>
        <button type="button" class="text-slate-400 hover:text-white p-1 rounded-lg" @click="onClose">
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="flex-1 min-h-0 flex flex-col" @submit.prevent="handleSubmit">
        <div class="flex-1 overflow-y-auto">
        <div class="p-5 space-y-4 text-xs border-b border-slate-800">
          <div class="flex items-center justify-between gap-3">
            <p class="text-slate-400">
              Trefwoord, rubriek en post bepalen waar mutaties automatisch naartoe gaan.
            </p>
            <label class="inline-flex items-center gap-2 text-slate-300 shrink-0 cursor-pointer">
              <input v-model="isActive" type="checkbox" class="w-4 h-4 accent-indigo-600 rounded" />
              <span class="font-semibold">{{ isActive ? "Actief" : "Gepauzeerd" }}</span>
            </label>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Naam</label>
            <input
              v-model="name"
              type="text"
              required
              placeholder="Bijv. Jumbo Supermarkt of Belastingdienst toeslagen"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Trefwoord</label>
            <input
              v-model="keyword"
              type="text"
              required
              placeholder="Bijv. Belastingdienst, AH , PLUS"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-400 focus:outline-none focus:border-indigo-500"
            />
            <p class="text-[10px] text-slate-500 mt-1">
              Matcht als dit stuk in de omschrijving of tegenpartij staat. Hoofdletters maken niet uit.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Rubriek</label>
              <select
                :value="targetGroup"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
                @change="handleGroupChange(($event.target as HTMLSelectElement).value)"
              >
                <option v-for="g in availableGroups" :key="g" :value="g">{{ g }}</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Begrotingspost</label>
              <select
                v-model="targetBudgetItemId"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                <option value="">(Geen specifieke post)</option>
                <option v-for="item in itemsInSelectedGroup" :key="item.id" :value="item.id">
                  {{ item.name }}
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Zoekveld</label>
              <select
                v-model="matchField"
                class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                <option value="description">Omschrijving</option>
                <option value="counterparty">Tegenpartij</option>
                <option value="both">Omschrijving en tegenpartij</option>
              </select>
            </div>
            <div>
              <label class="block text-slate-300 font-semibold mb-1">Richting</label>
              <div class="w-full bg-slate-800/80 border border-slate-700 rounded-xl px-3 py-2 text-slate-300">
                {{
                  targetType === "inkomsten"
                    ? "Alleen bijschrijvingen (inkomsten)"
                    : targetType === "sparen"
                      ? "Alleen stortingen naar sparen"
                      : "Alleen afschrijvingen (uitgaven)"
                }}
              </div>
              <p class="text-[10px] text-slate-500 mt-1">Volgt automatisch de gekozen rubriek.</p>
            </div>
          </div>
        </div>

        <div class="p-5 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <h4 class="text-sm font-bold text-white">Matches</h4>
              <p class="text-[11px] text-slate-400">
                {{ visibleMatches.length }} in deze periode
                · {{ allKeywordMatches.length }} totaal
                · {{ extraMatches.length }} nog ongekoppeld
              </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
              <select
                v-model="periodKey"
                class="bg-slate-800 border border-slate-700 rounded-xl px-3 py-1.5 text-white text-[11px] focus:outline-none focus:border-indigo-500 cursor-pointer"
              >
                <option v-for="option in periodOptions" :key="option.key" :value="option.key">
                  {{ option.label }}
                </option>
              </select>
              <div class="relative">
                <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                <input
                  v-model="searchTerm"
                  type="text"
                  placeholder="Filter mutaties..."
                  class="bg-slate-800 border border-slate-700 rounded-xl pl-8 pr-3 py-1.5 text-[11px] text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500 w-full sm:w-44"
                />
              </div>
            </div>
          </div>

          <div
            v-if="keyword.trim().length < 2"
            class="text-center py-8 border border-dashed border-slate-800 rounded-2xl text-slate-500 text-xs"
          >
            Typ minstens 2 tekens in het trefwoord om matches te zien.
          </div>
          <div
            v-else-if="visibleMatches.length === 0"
            class="text-center py-8 border border-dashed border-slate-800 rounded-2xl text-slate-500 text-xs"
          >
            Geen mutaties in deze periode met dit trefwoord.
          </div>
          <div v-else class="space-y-1.5 max-h-64 overflow-y-auto pr-1">
            <div
              v-for="tx in visibleMatches.slice(0, 80)"
              :key="tx.id"
              class="bg-slate-800/60 border border-slate-700/60 p-2.5 rounded-xl flex items-center justify-between gap-3"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <TransactionDate :date="tx.date" :time="tx.time" size="sm" />
                  <span class="text-white truncate">{{ tx.description }}</span>
                </div>
                <p class="text-[10px] text-slate-500 truncate mt-0.5">
                  {{ tx.budgetItemId ? tx.categoryGroup : "Ongekoppeld" }}
                  <span v-if="tx.counterparty"> · {{ tx.counterparty }}</span>
                </p>
              </div>
              <span
                class="font-mono font-bold shrink-0"
                :class="tx.amount > 0 ? 'text-emerald-400' : 'text-rose-400'"
              >
                {{ tx.amount > 0 ? "+" : "−" }}€ {{ euro(tx.amount) }}
              </span>
            </div>
            <p v-if="visibleMatches.length > 80" class="text-[10px] text-slate-500 px-1">
              + {{ visibleMatches.length - 80 }} meer in deze periode
            </p>
          </div>
        </div>
        </div>

        <div class="px-5 py-3 border-t border-slate-800 bg-slate-850 shrink-0 flex justify-end gap-2">
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
            {{
              isEditing
                ? "Wijzigingen opslaan"
                : extraMatches.length > 0
                  ? `Opslaan & ${extraMatches.length} rijen koppelen`
                  : "Koppelregel opslaan"
            }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
