<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from "vue";
import {
  ArrowLeftRight,
  ArrowDownRight,
  ArrowUpRight,
  Search,
  Filter,
  Plus,
  Sparkles,
  Sliders,
  CheckSquare,
  Square,
  Trash2,
  Tag,
  Check,
  AlertCircle,
  Link2,
  CheckCircle2,
  ChevronRight,
} from "lucide-vue-next";
import type {
  Transaction,
  BudgetCategoryGroup,
  Rule,
  BudgetItem,
  CategoryDefinition,
  BudgetType,
} from "../types";
import LinkTransactionModal from "./LinkTransactionModal.vue";
import TransactionDate from "./TransactionDate.vue";
import { isLinkExcludedTransaction, isUnlinkedTransaction, matchingUnlinkedTransactions } from "../matchRule";

const props = defineProps<{
  transactions: Transaction[];
  onAddTransaction: () => void;
  onOpenAutoProcess: () => void;
  onDeleteTransaction: (id: string) => void;
  onLinkTransaction: (
    txId: string,
    categoryGroup: BudgetCategoryGroup,
    budgetItemId: string,
    createRule?: {
      name: string;
      keyword: string;
      matchField: "description" | "counterparty" | "both";
      targetType: BudgetType;
    }
  ) => void;
  onCreateRuleFromTransaction: (
    keyword: string,
    targetGroup: BudgetCategoryGroup,
    targetType: "inkomsten" | "uitgaven" | "sparen"
  ) => void;
  onBulkUpdateCategory: (
    ids: string[],
    newCategory: BudgetCategoryGroup,
    newBudgetItemId?: string
  ) => void;
  rules: Rule[];
  budgetItems: BudgetItem[];
  categories: CategoryDefinition[];
  onOpenAddBudgetItemModal?: (group?: BudgetCategoryGroup) => void;
  initialFilter?: "ALL" | "UNLINKED" | "LINKED" | "Inkomsten" | "Uitgave" | "Sparen";
}>();

const searchTerm = ref("");
const filterType = ref<"ALL" | "UNLINKED" | "LINKED" | "Inkomsten" | "Uitgave" | "Sparen">(
  props.initialFilter ?? "ALL"
);
const amountDirection = ref<"all" | "out" | "in">("all");
const selectedCategory = ref("ALL");
const selectedTxIds = ref<string[]>([]);
const bulkCategory = ref<BudgetCategoryGroup>("Dagelijks Leven");
const bulkBudgetItemId = ref("");
const selectedTxForLinking = ref<Transaction | null>(null);
const justLinked = ref<{
  txId: string;
  budgetItemId: string;
  description: string;
  extraCount: number;
} | null>(null);

let justLinkedTimer: number | undefined;
watch(
  () => props.initialFilter,
  (value) => {
    if (value) {
      filterType.value = value;
    }
  }
);
watch(justLinked, (value) => {
  if (justLinkedTimer) window.clearTimeout(justLinkedTimer);
  if (!value) return;
  justLinkedTimer = window.setTimeout(() => {
    justLinked.value = null;
  }, 8000);
});
onUnmounted(() => {
  if (justLinkedTimer) window.clearTimeout(justLinkedTimer);
});

const budgetItemMap = computed(() => {
  const map = new Map<string, BudgetItem>();
  props.budgetItems.forEach((item) => map.set(item.id, item));
  return map;
});

const unlinkedCount = computed(
  () => props.transactions.filter((t) => isUnlinkedTransaction(t)).length
);
const excludedCount = computed(
  () => props.transactions.filter((t) => isLinkExcludedTransaction(t)).length
);
const linkedCount = computed(
  () => props.transactions.filter((t) => !isUnlinkedTransaction(t) && !isLinkExcludedTransaction(t)).length
);
const outgoingCount = computed(
  () => props.transactions.filter((t) => t.amount < 0).length
);
const incomingCount = computed(
  () => props.transactions.filter((t) => t.amount > 0).length
);

function toggleAmountDirection(next: "out" | "in") {
  amountDirection.value = amountDirection.value === next ? "all" : next;
}

function parseSearchAmount(
  raw: string
): { value: number; sign: "in" | "out" | null } | null {
  let value = raw.replace(/€/g, "").replace(/\s/g, "").trim();
  if (!value || !/\d/.test(value)) {
    return null;
  }

  let sign: "in" | "out" | null = null;
  if (value.startsWith("+")) {
    sign = "in";
    value = value.slice(1);
  } else if (value.startsWith("-") || value.startsWith("−")) {
    sign = "out";
    value = value.slice(1);
  }

  if (!/^\d[\d.,]*$/.test(value)) {
    return null;
  }

  const lastComma = value.lastIndexOf(",");
  const lastDot = value.lastIndexOf(".");
  if (lastComma >= 0 && lastDot >= 0) {
    value =
      lastComma > lastDot
        ? value.replace(/\./g, "").replace(",", ".")
        : value.replace(/,/g, "");
  } else if (lastComma >= 0) {
    value = value.replace(/\./g, "").replace(",", ".");
  } else if (lastDot >= 0) {
    const decimals = value.length - lastDot - 1;
    if (decimals === 3) {
      value = value.replace(/\./g, "");
    }
  }

  const amount = Number(value);
  if (!Number.isFinite(amount)) {
    return null;
  }
  return { value: Math.abs(amount), sign };
}

function amountsMatchSearch(absTx: number, parsed: number, rawToken: string): boolean {
  if (Math.abs(absTx - parsed) < 0.005) {
    return true;
  }
  const compact = rawToken.replace(/€/g, "").replace(/[+\-−\s]/g, "");
  const hasDecimal = /[.,]/.test(compact);
  if (!hasDecimal && Number.isInteger(parsed)) {
    return Math.floor(absTx + 1e-9) === parsed;
  }
  return false;
}

function transactionMatchesSearchToken(
  tx: Transaction,
  token: string,
  itemName: string | undefined
): boolean {
  const parsed = parseSearchAmount(token);
  if (parsed) {
    const signOk =
      (parsed.sign !== "out" || tx.amount < 0) &&
      (parsed.sign !== "in" || tx.amount > 0);
    if (signOk && amountsMatchSearch(Math.abs(tx.amount), parsed.value, token)) {
      return true;
    }
    if (parsed.sign) {
      return false;
    }
  }

  const needle = token.toLowerCase();
  return (
    tx.description.toLowerCase().includes(needle) ||
    (tx.counterparty && tx.counterparty.toLowerCase().includes(needle)) ||
    (!!itemName && itemName.toLowerCase().includes(needle)) ||
    tx.date.includes(needle)
  );
}

const filtered = computed(() =>
  props.transactions.filter((tx) => {
    const isUnlinked = isUnlinkedTransaction(tx);
    const isExcluded = isLinkExcludedTransaction(tx);
    const query = searchTerm.value.trim();
    if (query) {
      const itemName = tx.budgetItemId
        ? budgetItemMap.value.get(tx.budgetItemId)?.name
        : undefined;
      const tokens = query.split(/\s+/).filter(Boolean);
      const matchesSearch = tokens.every((token) =>
        transactionMatchesSearchToken(tx, token, itemName)
      );
      if (!matchesSearch) return false;
    }

    if (amountDirection.value === "out" && tx.amount >= 0) return false;
    if (amountDirection.value === "in" && tx.amount <= 0) return false;

    const isJustLinked = justLinked.value?.txId === tx.id;
    if (filterType.value === "UNLINKED" && (!isUnlinked || isExcluded) && !isJustLinked) return false;
    if (filterType.value === "LINKED" && (isUnlinked || isExcluded)) return false;
    if (filterType.value === "Inkomsten" && tx.type !== "Inkomsten") return false;
    if (filterType.value === "Uitgave" && tx.type !== "Uitgave") return false;
    if (filterType.value === "Sparen" && tx.type !== "Sparen") return false;
    if (selectedCategory.value !== "ALL" && tx.categoryGroup !== selectedCategory.value) return false;
    return true;
  })
);

const categoryOptions = computed(() =>
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

const bulkItemsInSelectedGroup = computed(() =>
  props.budgetItems.filter((i) => i.group === bulkCategory.value)
);

const filterTabs = computed(() => [
  { id: "ALL" as const, label: `Alle (${props.transactions.length})`, isWarning: false },
  {
    id: "UNLINKED" as const,
    label: `Niet direct gekoppeld (${unlinkedCount.value})`,
    isWarning: unlinkedCount.value > 0,
  },
  {
    id: "LINKED" as const,
    label: `Gekoppeld (${linkedCount.value})${excludedCount.value > 0 ? ` · ${excludedCount.value} uitgesloten` : ""}`,
    isWarning: false,
  },
  {
    id: "Uitgave" as const,
    label: `Uitgaven (${props.transactions.filter((t) => t.type === "Uitgave").length})`,
    isWarning: false,
  },
  {
    id: "Inkomsten" as const,
    label: `Inkomsten (${props.transactions.filter((t) => t.type === "Inkomsten").length})`,
    isWarning: false,
  },
  {
    id: "Sparen" as const,
    label: `Sparen (${props.transactions.filter((t) => t.type === "Sparen").length})`,
    isWarning: false,
  },
]);

function handleSelectAll() {
  if (selectedTxIds.value.length === filtered.value.length) {
    selectedTxIds.value = [];
  } else {
    selectedTxIds.value = filtered.value.map((t) => t.id);
  }
}

function toggleSelectOne(id: string) {
  if (selectedTxIds.value.includes(id)) {
    selectedTxIds.value = selectedTxIds.value.filter((x) => x !== id);
  } else {
    selectedTxIds.value = [...selectedTxIds.value, id];
  }
}

function handleApplyBulkCategory() {
  if (selectedTxIds.value.length === 0) return;
  props.onBulkUpdateCategory(
    selectedTxIds.value,
    bulkCategory.value,
    bulkBudgetItemId.value || undefined
  );
  selectedTxIds.value = [];
}

function onBulkCategoryChange(grp: BudgetCategoryGroup) {
  bulkCategory.value = grp;
  const itemsInGrp = props.budgetItems.filter((i) => i.group === grp);
  bulkBudgetItemId.value = itemsInGrp.length > 0 ? itemsInGrp[0].id : "";
}

function handleLink(
  txId: string,
  group: BudgetCategoryGroup,
  budgetItemId: string,
  createRule?: {
    name: string;
    keyword: string;
    matchField: "description" | "counterparty" | "both";
    targetType: BudgetType;
  }
) {
  const tx = props.transactions.find((item) => item.id === txId);
  props.onLinkTransaction(txId, group, budgetItemId, createRule);
  justLinked.value = {
    txId,
    budgetItemId,
    description: tx?.description ?? "Transactie",
    extraCount: createRule
      ? matchingUnlinkedTransactions(
          props.transactions,
          createRule.keyword,
          createRule.matchField,
          txId,
          createRule.targetType
        ).length
      : 0,
  };
}

function createRuleFromTx(tx: Transaction) {
  const kw = tx.counterparty || tx.description.split(" ")[0];
  const grp = tx.categoryGroup !== "Ongecategoriseerd" ? tx.categoryGroup : "Dagelijks Leven";
  const type =
    tx.type === "Inkomsten" ? "inkomsten" : tx.type === "Sparen" ? "sparen" : "uitgaven";
  props.onCreateRuleFromTransaction(kw, grp, type);
}
</script>

<template>
  <div id="transactions-view" class="space-y-6">
    <div
      class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4"
    >
      <div>
        <div class="flex items-center gap-2">
          <ArrowLeftRight class="w-5 h-5 text-indigo-400" />
          <h2 class="text-xl font-bold text-white tracking-tight">
            Banktransacties & Mutaties ({{ transactions.length }})
          </h2>
        </div>
        <p class="text-xs text-slate-400 mt-1">
          Realtime feed afkomstig van ING Bankrekening • Koppel transacties direct aan begrotingsposten en
          automatiseer met koppelregels
        </p>
      </div>
      <div class="flex items-center gap-3">
        <button
          v-if="unlinkedCount > 0"
          type="button"
          class="flex items-center gap-1.5 bg-violet-600 hover:bg-violet-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition-all active:scale-95"
          @click="onOpenAutoProcess"
        >
          <Sparkles class="w-4 h-4" />
          <span>Automatisch verwerken</span>
        </button>
        <button
          type="button"
          class="flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white px-3.5 py-2 rounded-xl text-xs font-semibold shadow-sm transition-all active:scale-95"
          @click="onAddTransaction"
        >
          <Plus class="w-4 h-4" />
          <span>Transactie Toevoegen</span>
        </button>
      </div>
    </div>

    <div
      v-if="justLinked"
      id="just-linked-banner"
      class="bg-emerald-950/70 border border-emerald-600/40 p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs"
    >
      <div class="flex items-start sm:items-center gap-3 min-w-0">
        <div class="p-2 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shrink-0">
          <CheckCircle2 class="w-5 h-5" />
        </div>
        <div class="min-w-0">
          <p class="font-bold text-white text-sm">
            Gekoppeld aan {{ budgetItemMap.get(justLinked.budgetItemId)?.name ?? "begrotingspost" }}
          </p>
          <p class="text-emerald-200/80 text-xs mt-0.5 truncate">
            {{ justLinked.description
            }}{{
              justLinked.extraCount > 0
                ? ` · plus ${justLinked.extraCount} ${justLinked.extraCount === 1 ? "andere rij" : "andere rijen"}`
                : ""
            }}{{ unlinkedCount > 0 ? ` · nog ${unlinkedCount} te koppelen` : "" }}
          </p>
        </div>
      </div>
      <button
        type="button"
        class="text-emerald-300 hover:text-white font-semibold px-3 py-1.5 rounded-xl hover:bg-emerald-900/60 transition-colors shrink-0"
        @click="justLinked = null"
      >
        Sluiten
      </button>
    </div>

    <div
      v-if="unlinkedCount > 0 && filterType !== 'UNLINKED'"
      class="bg-gradient-to-r from-amber-950/70 to-slate-900 border border-amber-600/40 p-4 rounded-2xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-sm"
    >
      <div class="flex items-start sm:items-center gap-3">
        <div class="p-2 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30 shrink-0">
          <AlertCircle class="w-5 h-5" />
        </div>
        <div>
          <p class="font-bold text-white text-sm">
            {{ unlinkedCount }}
            {{ unlinkedCount === 1 ? "transactie is" : "transacties zijn" }} nog niet direct gekoppeld
            aan een begrotingspost
          </p>
          <p class="text-amber-200/80 text-xs mt-0.5">
            Koppel deze mutaties aan een specifieke post zodat je werkelijke maanduitgaven 1-op-1 kloppen met
            je begroting.
          </p>
        </div>
      </div>
      <button
        type="button"
        class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold px-3.5 py-2 rounded-xl text-xs transition-colors shrink-0 flex items-center justify-center gap-1.5 shadow-md shadow-amber-500/20"
        @click="filterType = 'UNLINKED'"
      >
        <span>Toon {{ unlinkedCount }} ongekoppelde</span>
        <ChevronRight class="w-3.5 h-3.5" />
      </button>
    </div>

    <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow-sm">
      <div class="flex items-center gap-2 overflow-x-auto">
        <div class="relative flex-1 min-w-[12rem]">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            v-model="searchTerm"
            type="text"
            placeholder="Zoek op omschrijving, bedrijf of bedrag (bv. Albert Heijn, 45,80, -8,39)..."
            class="w-full bg-slate-800/80 border border-slate-700 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
          />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <Filter class="w-4 h-4 text-slate-400" />
          <select
            v-model="selectedCategory"
            class="bg-slate-800 border border-slate-700 text-slate-200 text-xs rounded-xl px-3 py-2 focus:outline-none focus:border-indigo-500 cursor-pointer"
          >
            <option value="ALL">Alle Categorieën</option>
            <option v-for="cat in categoryOptions" :key="cat" :value="cat">{{ cat }}</option>
          </select>
        </div>
        <div class="flex items-center bg-slate-800/90 rounded-xl p-1 border border-slate-700 text-xs shrink-0">
          <button
            v-for="tab in filterTabs"
            :key="tab.id"
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium transition-all flex items-center gap-1 whitespace-nowrap"
            :class="
              filterType === tab.id
                ? tab.id === 'UNLINKED' && tab.isWarning
                  ? 'bg-amber-500 text-slate-950 font-semibold shadow-sm shadow-amber-500/20'
                  : 'bg-indigo-600 text-white font-semibold'
                : tab.id === 'UNLINKED' && tab.isWarning
                  ? 'bg-amber-500/20 text-amber-300 font-semibold hover:bg-amber-500/30'
                  : 'text-slate-400 hover:text-white'
            "
            @click="filterType = tab.id"
          >
            <AlertCircle v-if="tab.id === 'UNLINKED' && tab.isWarning" class="w-3.5 h-3.5" />
            <span>{{ tab.label }}</span>
          </button>
        </div>
        <div class="flex items-center bg-slate-800/90 rounded-xl p-1 border border-slate-700 text-xs shrink-0">
          <button
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium transition-all flex items-center gap-1 whitespace-nowrap"
            :class="
              amountDirection === 'out'
                ? 'bg-indigo-600 text-white font-semibold'
                : 'text-slate-400 hover:text-white'
            "
            @click="toggleAmountDirection('out')"
          >
            <ArrowDownRight class="w-3.5 h-3.5" />
            <span>Eraf ({{ outgoingCount }})</span>
          </button>
          <button
            type="button"
            class="px-2.5 py-1 rounded-lg font-medium transition-all flex items-center gap-1 whitespace-nowrap"
            :class="
              amountDirection === 'in'
                ? 'bg-indigo-600 text-white font-semibold'
                : 'text-slate-400 hover:text-white'
            "
            @click="toggleAmountDirection('in')"
          >
            <ArrowUpRight class="w-3.5 h-3.5" />
            <span>Erbij ({{ incomingCount }})</span>
          </button>
        </div>
      </div>
    </div>

    <div
      v-if="selectedTxIds.length > 0"
      class="bg-indigo-950/90 border border-indigo-700/80 p-3.5 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-3 text-xs text-white shadow-lg animate-in fade-in"
    >
      <div class="flex items-center gap-2">
        <span class="bg-indigo-600 px-2.5 py-1 rounded-lg font-bold text-xs shadow-sm">
          {{ selectedTxIds.length }}
        </span>
        <span class="font-medium">transacties geselecteerd voor bulkactie</span>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <select
          :value="bulkCategory"
          class="bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none"
          @change="onBulkCategoryChange(($event.target as HTMLSelectElement).value)"
        >
          <option v-for="c in categoryOptions" :key="c" :value="c">{{ c }}</option>
        </select>
        <select
          v-model="bulkBudgetItemId"
          class="bg-slate-900 border border-slate-700 text-white text-xs rounded-lg px-2.5 py-1.5 focus:outline-none"
        >
          <option value="">(Optioneel: kies post)</option>
          <option v-for="item in bulkItemsInSelectedGroup" :key="item.id" :value="item.id">
            {{ item.name }}
          </option>
        </select>
        <button
          type="button"
          class="bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-3.5 py-1.5 rounded-lg transition-colors flex items-center gap-1 shadow-sm active:scale-95"
          @click="handleApplyBulkCategory"
        >
          <Check class="w-3.5 h-3.5" />
          <span>Toewijzen aan {{ selectedTxIds.length }} transacties</span>
        </button>
        <button
          type="button"
          class="bg-slate-800 hover:bg-slate-700 text-slate-300 px-2.5 py-1.5 rounded-lg transition-colors"
          @click="selectedTxIds = []"
        >
          Deselecteer
        </button>
      </div>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-slate-800 bg-slate-950/50 text-slate-400 font-semibold uppercase tracking-wider">
              <th class="py-3.5 px-3 w-10 text-center">
                <button type="button" class="text-slate-400 hover:text-white" @click="handleSelectAll">
                  <CheckSquare
                    v-if="selectedTxIds.length === filtered.length && filtered.length > 0"
                    class="w-4 h-4 text-indigo-400"
                  />
                  <Square v-else class="w-4 h-4" />
                </button>
              </th>
              <th class="py-3.5 px-3 whitespace-nowrap">Datum & Tijd</th>
              <th class="py-3.5 px-3">Omschrijving / Tegenpartij</th>
              <th class="py-3.5 px-3">Rubriek & Begrotingspost</th>
              <th class="py-3.5 px-3">Bron</th>
              <th class="py-3.5 px-3 text-right">Bedrag</th>
              <th class="py-3.5 px-3 text-center">Acties</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-800">
            <tr v-if="filtered.length === 0">
              <td colspan="7" class="py-12 text-center text-slate-400 space-y-2">
                <AlertCircle class="w-8 h-8 mx-auto text-slate-500 opacity-60" />
                <p class="font-semibold text-sm text-slate-300">Geen transacties gevonden</p>
                <p class="text-xs text-slate-500">Pas de filters of zoekterm aan om resultaten te tonen.</p>
              </td>
            </tr>
            <tr
              v-for="tx in filtered"
              :key="tx.id"
              class="hover:bg-slate-800/40 transition-colors"
              :class="
                justLinked?.txId === tx.id
                  ? 'bg-emerald-950/40 ring-1 ring-inset ring-emerald-600/40'
                  : selectedTxIds.includes(tx.id)
                    ? 'bg-indigo-950/30'
                    : !(tx.budgetItemId && tx.categoryGroup !== 'Ongecategoriseerd')
                      ? 'bg-amber-950/10 hover:bg-amber-950/20'
                      : ''
              "
            >
              <td class="py-3 px-3 text-center">
                <button
                  type="button"
                  class="text-slate-400 hover:text-white"
                  @click="toggleSelectOne(tx.id)"
                >
                  <CheckSquare
                    v-if="selectedTxIds.includes(tx.id)"
                    class="w-4 h-4 text-indigo-400"
                  />
                  <Square v-else class="w-4 h-4" />
                </button>
              </td>
              <td class="py-3 px-3 whitespace-nowrap">
                <TransactionDate :date="tx.date" :time="tx.time" />
              </td>
              <td class="py-3 px-3">
                <div class="font-semibold text-white max-w-sm sm:max-w-md truncate">
                  {{ tx.description }}
                </div>
                <div
                  v-if="tx.counterparty"
                  class="text-[11px] text-slate-400 flex items-center gap-1.5 mt-0.5"
                >
                  <span class="text-slate-300">Tegenpartij: {{ tx.counterparty }}</span>
                  <template v-if="tx.accountIban">
                    <span class="text-slate-600">•</span>
                    <span class="font-mono text-[10px] text-slate-500">{{ tx.accountIban }}</span>
                  </template>
                </div>
              </td>
              <td class="py-3 px-3">
                <div v-if="tx.linkExcluded" class="space-y-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span
                      class="inline-flex items-center gap-1 bg-slate-700/60 text-slate-300 border border-slate-600 px-2 py-0.5 rounded-lg font-medium text-[11px]"
                      :title="tx.linkExclusionReason || 'Deze mutatie wordt niet gekoppeld aan een rubriek'"
                    >
                      <CheckCircle2 class="w-3 h-3 text-slate-400" />
                      Niet koppelen
                    </span>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-500 text-white px-2.5 py-1 rounded-lg font-semibold text-[11px] transition-all shadow-sm active:scale-95"
                      title="Toch aan een begrotingspost koppelen"
                      @click="selectedTxForLinking = tx"
                    >
                      <Link2 class="w-3 h-3" />
                      <span>Toch koppelen</span>
                    </button>
                  </div>
                  <div v-if="tx.linkExclusionReason" class="text-[10px] text-slate-500 leading-snug">
                    {{ tx.linkExclusionReason }}
                  </div>
                </div>
                <div v-else-if="tx.budgetItemId && tx.categoryGroup !== 'Ongecategoriseerd'" class="space-y-1">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 font-semibold text-white bg-indigo-950/60 hover:bg-indigo-900/80 border border-indigo-700/60 px-2 py-0.5 rounded-lg text-xs transition-colors group"
                      title="Klik om de gekoppelde begrotingspost te wijzigen"
                      @click="selectedTxForLinking = tx"
                    >
                      <Tag class="w-3 h-3 text-indigo-400 group-hover:scale-110 transition-transform" />
                      <span>{{ budgetItemMap.get(tx.budgetItemId)?.name ?? tx.budgetItemId }}</span>
                    </button>
                    <span
                      v-if="tx.matchedRuleId"
                      class="text-[9px] bg-slate-800 text-indigo-300 border border-slate-700 px-1.5 py-0.5 rounded font-mono"
                      title="Automatisch gematcht via koppelregel"
                    >
                      Regel
                    </span>
                  </div>
                  <div class="text-[10px] text-slate-400 font-medium">
                    Rubriek: <span class="text-slate-300">{{ tx.categoryGroup }}</span>
                  </div>
                </div>
                <div v-else class="space-y-1.5">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span
                      class="inline-flex items-center gap-1 bg-amber-500/15 text-amber-300 border border-amber-500/30 px-2 py-0.5 rounded-lg font-medium text-[11px]"
                    >
                      <AlertCircle class="w-3 h-3 text-amber-400" />
                      Niet direct gekoppeld
                    </span>
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 bg-indigo-600 hover:bg-indigo-500 text-white px-2.5 py-1 rounded-lg font-semibold text-[11px] transition-all shadow-sm active:scale-95"
                      title="Koppel deze transactie aan een begrotingspost en maak eventueel een regel"
                      @click="selectedTxForLinking = tx"
                    >
                      <Link2 class="w-3 h-3" />
                      <span>Koppel aan post...</span>
                    </button>
                  </div>
                  <div
                    v-if="tx.categoryGroup && tx.categoryGroup !== 'Ongecategoriseerd'"
                    class="text-[10px] text-slate-500"
                  >
                    Voorlopige groep: {{ tx.categoryGroup }}
                  </div>
                </div>
              </td>
              <td class="py-3 px-3">
                <span
                  class="text-[10px] bg-slate-800/80 text-slate-400 px-2 py-0.5 rounded border border-slate-700 font-mono"
                >
                  {{ tx.source }}
                </span>
              </td>
              <td
                class="py-3 px-3 text-right font-mono font-bold text-sm whitespace-nowrap"
                :class="tx.amount > 0 ? 'text-emerald-400' : 'text-rose-400'"
              >
                {{ tx.amount > 0 ? "+" : "" }}€
                {{ Math.abs(tx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
              </td>
              <td class="py-3 px-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button
                    v-if="!tx.linkExcluded"
                    type="button"
                    class="p-1.5 rounded-lg transition-colors"
                    :class="
                      tx.budgetItemId && tx.categoryGroup !== 'Ongecategoriseerd'
                        ? 'text-slate-400 hover:text-indigo-400 hover:bg-slate-800'
                        : 'text-amber-400 hover:text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30'
                    "
                    :title="
                      tx.budgetItemId && tx.categoryGroup !== 'Ongecategoriseerd'
                        ? 'Wijzig gekoppelde post'
                        : 'Koppel aan begrotingspost'
                    "
                    @click="selectedTxForLinking = tx"
                  >
                    <Link2 class="w-3.5 h-3.5" />
                  </button>
                  <button
                    v-if="!tx.linkExcluded"
                    type="button"
                    class="p-1.5 text-slate-400 hover:text-indigo-400 hover:bg-slate-800 rounded-lg transition-colors"
                    title="Maak automatische koppelregel voor dit trefwoord"
                    @click="createRuleFromTx(tx)"
                  >
                    <Sliders class="w-3.5 h-3.5" />
                  </button>
                  <button
                    type="button"
                    class="p-1.5 text-slate-400 hover:text-rose-400 hover:bg-slate-800 rounded-lg transition-colors"
                    title="Verwijder transactie"
                    @click="onDeleteTransaction(tx.id)"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <LinkTransactionModal
      :is-open="!!selectedTxForLinking"
      :on-close="() => (selectedTxForLinking = null)"
      :transaction="selectedTxForLinking"
      :transactions="transactions"
      :budget-items="budgetItems"
      :categories="categories"
      :on-link="handleLink"
      :on-open-add-budget-item-modal="onOpenAddBudgetItemModal"
    />
  </div>
</template>
