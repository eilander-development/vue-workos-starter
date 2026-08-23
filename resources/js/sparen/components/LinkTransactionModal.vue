<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
  X,
  Link2,
  Sliders,
  CheckCircle2,
  AlertCircle,
  FolderPlus,
} from "lucide-vue-next";
import type {
  Transaction,
  BudgetItem,
  BudgetCategoryGroup,
  CategoryDefinition,
  BudgetType,
} from "../types";
import TransactionDate from "./TransactionDate.vue";
import { extractSmartKeyword, matchingUnlinkedTransactions } from "../matchRule";

const props = defineProps<{
  isOpen: boolean;
  onClose: () => void;
  transaction: Transaction | null;
  transactions: Transaction[];
  budgetItems: BudgetItem[];
  categories: CategoryDefinition[];
  onLink: (
    txId: string,
    group: BudgetCategoryGroup,
    budgetItemId: string,
    createRule?: {
      name: string;
      keyword: string;
      matchField: "description" | "counterparty" | "both";
      targetType: BudgetType;
    }
  ) => void;
  onOpenAddBudgetItemModal?: (group?: BudgetCategoryGroup) => void;
}>();

const selectedGroup = ref<BudgetCategoryGroup>("Dagelijks Leven");
const selectedBudgetItemId = ref("");
const shouldCreateRule = ref(true);
const ruleName = ref("");
const ruleKeyword = ref("");
const ruleMatchField = ref<"description" | "counterparty" | "both">("description");

watch(
  () => [props.transaction, props.budgetItems] as const,
  () => {
    const transaction = props.transaction;
    if (!transaction) return;

    const initialGroup =
      transaction.categoryGroup && transaction.categoryGroup !== "Ongecategoriseerd"
        ? transaction.categoryGroup
        : transaction.type === "Inkomsten"
          ? "Inkomsten"
          : transaction.type === "Sparen"
            ? "Spaargeld"
            : "Dagelijks Leven";

    selectedGroup.value = initialGroup;

    if (transaction.budgetItemId) {
      selectedBudgetItemId.value = transaction.budgetItemId;
    } else {
      const firstInGroup = props.budgetItems.find((i) => i.group === initialGroup);
      selectedBudgetItemId.value = firstInGroup ? firstInGroup.id : "";
    }

    const smartKw = extractSmartKeyword(transaction);
    ruleKeyword.value = smartKw;
    ruleName.value = `Automatisch: ${smartKw}`;
    ruleMatchField.value = "description";
    shouldCreateRule.value = true;
  },
  { immediate: true }
);

function handleGroupChange(newGroup: BudgetCategoryGroup) {
  selectedGroup.value = newGroup;
  const itemsInNewGroup = props.budgetItems.filter((i) => i.group === newGroup);
  if (itemsInNewGroup.length > 0) {
    const matchStill = itemsInNewGroup.find((i) => i.id === selectedBudgetItemId.value);
    if (!matchStill) {
      selectedBudgetItemId.value = itemsInNewGroup[0].id;
    }
  } else {
    selectedBudgetItemId.value = "";
  }
}

const extraMatches = computed(() =>
  shouldCreateRule.value
    ? matchingUnlinkedTransactions(
        props.transactions,
        ruleKeyword.value,
        ruleMatchField.value,
        props.transaction?.id
      )
    : []
);

const itemsInSelectedGroup = computed(() =>
  props.budgetItems.filter((i) => i.group === selectedGroup.value)
);
const selectedItemObj = computed(() =>
  props.budgetItems.find((i) => i.id === selectedBudgetItemId.value)
);
const isIncome = computed(() => (props.transaction?.amount ?? 0) > 0);

function handleSubmit() {
  const transaction = props.transaction;
  if (!transaction || !selectedBudgetItemId.value) return;

  const matchedCat = props.categories.find((c) => c.name === selectedGroup.value);
  const targetType: BudgetType =
    selectedGroup.value === "Inkomsten" || matchedCat?.type === "inkomsten"
      ? "inkomsten"
      : selectedGroup.value === "Spaargeld" || matchedCat?.type === "sparen"
        ? "sparen"
        : "uitgaven";

  const ruleData =
    shouldCreateRule.value && ruleKeyword.value.trim()
      ? {
          name: ruleName.value.trim() || `Regel: ${ruleKeyword.value.trim()}`,
          keyword: ruleKeyword.value.trim(),
          matchField: ruleMatchField.value,
          targetType,
        }
      : undefined;

  props.onLink(transaction.id, selectedGroup.value, selectedBudgetItemId.value, ruleData);
  props.onClose();
}

function openAddBudgetItem() {
  props.onClose();
  props.onOpenAddBudgetItemModal?.(selectedGroup.value);
}
</script>

<template>
  <div
    v-if="isOpen && transaction"
    id="link-transaction-modal-overlay"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-sm animate-in fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="p-2 rounded-xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
            <Link2 class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">Transactie Koppelen aan Post</h3>
            <p class="text-xs text-slate-400">
              Wijs deze mutatie toe aan een begrotingspost en maak eventueel direct een herkenningsregel
            </p>
          </div>
        </div>
        <button
          type="button"
          class="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors"
          @click="onClose"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-5 space-y-4 overflow-y-auto text-xs" @submit.prevent="handleSubmit">
        <div class="bg-slate-800/80 border border-slate-700/80 p-3.5 rounded-xl space-y-2">
          <div class="flex items-start justify-between gap-3">
            <div class="space-y-0.5">
              <div class="flex items-center gap-2">
                <TransactionDate :date="transaction.date" :time="transaction.time" size="sm" />
                <span class="text-[10px] bg-slate-700 text-slate-300 px-1.5 py-0.2 rounded font-mono">
                  {{ transaction.source }}
                </span>
              </div>
              <h4 class="font-semibold text-white text-sm break-words">{{ transaction.description }}</h4>
              <p v-if="transaction.counterparty" class="text-slate-400 text-[11px]">
                Tegenpartij:
                <span class="text-slate-300 font-medium">{{ transaction.counterparty }}</span>
              </p>
            </div>
            <div
              class="font-mono font-bold text-base shrink-0"
              :class="isIncome ? 'text-emerald-400' : 'text-rose-400'"
            >
              {{ isIncome ? "+" : "" }}€
              {{ Math.abs(transaction.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </div>
          </div>

          <div class="pt-2 border-t border-slate-700/60 flex items-center justify-between text-[11px]">
            <span class="text-slate-400">Huidige status:</span>
            <span
              v-if="transaction.budgetItemId"
              class="text-indigo-300 flex items-center gap-1 font-medium bg-indigo-950/60 border border-indigo-800/60 px-2 py-0.5 rounded-md"
            >
              <CheckCircle2 class="w-3 h-3 text-indigo-400" />
              Gekoppeld ({{ transaction.categoryGroup }})
            </span>
            <span
              v-else
              class="text-amber-400 flex items-center gap-1 font-medium bg-amber-950/60 border border-amber-800/60 px-2 py-0.5 rounded-md"
            >
              <AlertCircle class="w-3 h-3 text-amber-400" />
              Niet direct gekoppeld aan post
            </span>
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="block text-slate-200 font-semibold flex items-center justify-between">
            <span>1. Kies Categorie / Rubriek</span>
            <span class="text-[10px] text-slate-400 font-normal">Hoofdgroep in de begroting</span>
          </label>
          <select
            :value="selectedGroup"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500 font-medium cursor-pointer"
            @change="handleGroupChange(($event.target as HTMLSelectElement).value)"
          >
            <option v-for="cat in categories" :key="cat.id" :value="cat.name">
              {{ cat.name }} ({{
                cat.type === "inkomsten" ? "Inkomsten" : cat.type === "sparen" ? "Sparen" : "Uitgaven"
              }})
            </option>
          </select>
        </div>

        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <label class="block text-slate-200 font-semibold">2. Kies Specifieke Begrotingspost</label>
            <button
              v-if="onOpenAddBudgetItemModal"
              type="button"
              class="text-indigo-400 hover:text-indigo-300 text-[11px] font-medium flex items-center gap-1"
              @click="openAddBudgetItem"
            >
              <FolderPlus class="w-3 h-3" />
              <span>+ Nieuwe post toevoegen</span>
            </button>
          </div>

          <div
            v-if="itemsInSelectedGroup.length === 0"
            class="p-3 bg-slate-800/50 border border-slate-700/50 rounded-xl text-center text-slate-400 space-y-2"
          >
            <p>Er zijn nog geen posten in categorie "{{ selectedGroup }}".</p>
            <button
              v-if="onOpenAddBudgetItemModal"
              type="button"
              class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded-lg text-xs font-semibold"
              @click="openAddBudgetItem"
            >
              <FolderPlus class="w-3.5 h-3.5" />
              <span>Post Aanmaken in {{ selectedGroup }}</span>
            </button>
          </div>
          <select
            v-else
            v-model="selectedBudgetItemId"
            required
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2.5 text-white focus:outline-none focus:border-indigo-500 font-medium cursor-pointer"
          >
            <option value="" disabled>-- Selecteer een begrotingspost --</option>
            <option v-for="item in itemsInSelectedGroup" :key="item.id" :value="item.id">
              {{ item.name }} (Budget: €
              {{ item.actual.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }})
            </option>
          </select>

          <div
            v-if="selectedItemObj"
            class="p-2.5 bg-indigo-950/30 border border-indigo-800/40 rounded-xl flex items-center justify-between text-[11px] text-indigo-300"
          >
            <span>
              Geselecteerde post: <strong>{{ selectedItemObj.name }}</strong>
            </span>
            <span>
              Huidig betaald/ontvangen: €
              {{ selectedItemObj.paidOrReceived.toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
            </span>
          </div>
        </div>

        <div class="pt-2 border-t border-slate-800 space-y-3">
          <div class="bg-slate-800/90 border border-slate-700/80 rounded-xl p-3.5 space-y-3">
            <label class="flex items-start gap-3 cursor-pointer select-none">
              <input
                v-model="shouldCreateRule"
                type="checkbox"
                class="mt-0.5 w-4 h-4 accent-indigo-600 rounded cursor-pointer shrink-0"
              />
              <div>
                <span class="font-bold text-white text-xs block flex items-center gap-1.5">
                  <Sliders class="w-3.5 h-3.5 text-indigo-400" />
                  Maak hier direct een automatische koppelregel voor
                </span>
                <span class="text-[11px] text-slate-400 block mt-0.5">
                  Toekomstige mutaties én alle nog ongekoppelde rijen met dit trefwoord gaan naar
                  <strong class="text-slate-200">{{ selectedItemObj?.name || "deze post" }}</strong>.
                </span>
              </div>
            </label>

            <div
              v-if="shouldCreateRule"
              class="pt-3 border-t border-slate-700/60 space-y-3 pl-7 animate-in fade-in"
            >
              <div>
                <label class="block text-slate-300 font-semibold mb-1">
                  Trefwoord om automatisch op te filteren
                </label>
                <input
                  v-model="ruleKeyword"
                  type="text"
                  :required="shouldCreateRule"
                  placeholder="Bijv. PLUS, Albert Heijn, Kruidvat, Shell..."
                  class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-1.5 text-white font-mono placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                <div>
                  <label class="block text-slate-300 font-semibold mb-1">Naam van de regel</label>
                  <input
                    v-model="ruleName"
                    type="text"
                    placeholder="Naam voor het overzicht"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-1.5 text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                  />
                </div>
                <div>
                  <label class="block text-slate-300 font-semibold mb-1">Zoek in veld</label>
                  <select
                    v-model="ruleMatchField"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-1.5 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
                  >
                    <option value="description">Omschrijving</option>
                    <option value="counterparty">Tegenpartij</option>
                    <option value="both">Beide velden</option>
                  </select>
                </div>
              </div>

              <p v-if="ruleKeyword.trim().length < 2" class="text-[11px] text-amber-300">
                Typ minstens 2 tekens om te zien welke ongekoppelde rijen meegaan.
              </p>
              <div v-else class="bg-indigo-950/40 border border-indigo-800/50 rounded-lg p-2.5 space-y-1.5">
                <p class="text-[11px] text-indigo-200 font-semibold">
                  {{
                    extraMatches.length === 0
                      ? "Geen andere ongekoppelde rijen voldoen aan dit trefwoord."
                      : `${extraMatches.length} ${extraMatches.length === 1 ? "andere ongekoppelde rij voldoet" : "andere ongekoppelde rijen voldoen"} en worden nu ook gekoppeld.`
                  }}
                </p>
                <ul v-if="extraMatches.length > 0" class="space-y-1">
                  <li
                    v-for="tx in extraMatches.slice(0, 4)"
                    :key="tx.id"
                    class="text-[10px] text-slate-400 truncate"
                  >
                    {{ tx.date }} · {{ tx.description }}
                  </li>
                  <li v-if="extraMatches.length > 4" class="text-[10px] text-slate-500">
                    + {{ extraMatches.length - 4 }} meer
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-800 flex items-center justify-between gap-2 shrink-0">
          <span class="text-[11px] text-slate-500">
            {{
              selectedBudgetItemId
                ? `Wordt geboekt op ${selectedItemObj?.name}`
                : "Kies eerst een begrotingspost"
            }}
          </span>

          <div class="flex items-center gap-2">
            <button
              type="button"
              class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl font-medium transition-all active:scale-95"
              @click="onClose"
            >
              Annuleren
            </button>
            <button
              type="submit"
              :disabled="!selectedBudgetItemId"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-all shadow-md shadow-indigo-600/20 flex items-center gap-1.5 active:scale-95"
            >
              <CheckCircle2 class="w-4 h-4" />
              <span>
                {{
                  shouldCreateRule
                    ? extraMatches.length > 0
                      ? `Koppelen & ${extraMatches.length + 1} rijen toewijzen`
                      : "Koppelen & Regel Opslaan"
                    : "Alleen Transactie Koppelen"
                }}
              </span>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>
