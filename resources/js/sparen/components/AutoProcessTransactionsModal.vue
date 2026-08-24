<script setup lang="ts">
import { computed, ref, watch } from "vue";
import {
  X,
  Sparkles,
  CheckSquare,
  Square,
  ChevronDown,
  ChevronRight,
  AlertCircle,
  CheckCircle2,
  MinusSquare,
} from "lucide-vue-next";
import type {
  BankAccount,
  BudgetCategoryGroup,
  BudgetItem,
  BudgetType,
  CategoryDefinition,
  Rule,
  SavingsGoal,
  Transaction,
  MonthlyBudget,
} from "../types";
import TransactionDate from "./TransactionDate.vue";
import {
  getTransactionLinkSuggestionService,
  type AutoProcessSaveAssignment,
  type TransactionLinkSuggestionGroup,
  type TransactionLinkSuggestionResult,
  type SuggestionConfidence,
} from "../services/transactionLinkSuggestions";

interface TxAssignmentState {
  selected: boolean;
  categoryGroup: BudgetCategoryGroup;
  budgetItemId: string;
  targetType: BudgetType;
  customTarget: boolean;
}

const props = defineProps<{
  isOpen: boolean;
  onClose: () => void;
  transactions: Transaction[];
  rules: Rule[];
  savingsGoals: SavingsGoal[];
  budgetItems: BudgetItem[];
  categories: CategoryDefinition[];
  bankAccounts: BankAccount[];
  currentMonth: MonthlyBudget;
  onSave: (assignments: AutoProcessSaveAssignment[]) => void;
}>();

const result = ref<TransactionLinkSuggestionResult | null>(null);
const txStates = ref<Map<string, TxAssignmentState>>(new Map());
const groupMeta = ref<Map<string, TransactionLinkSuggestionGroup>>(new Map());
const createRuleGroupIds = ref<Set<string>>(new Set());
const expandedGroupIds = ref<Set<string>>(new Set());
const editingTxIds = ref<Set<string>>(new Set());

const transactionMap = computed(() => {
  const map = new Map<string, Transaction>();
  props.transactions.forEach((tx) => map.set(tx.id, tx));
  return map;
});

const groupedByCategory = computed(() => {
  if (!result.value) {
    return [] as Array<{
      categoryGroup: string;
      groups: TransactionLinkSuggestionGroup[];
    }>;
  }

  const map = new Map<string, TransactionLinkSuggestionGroup[]>();
  for (const group of result.value.groups) {
    const existing = map.get(group.categoryGroup) ?? [];
    existing.push(group);
    map.set(group.categoryGroup, existing);
  }

  return [...map.entries()]
    .sort(([left], [right]) => left.localeCompare(right, "nl"))
    .map(([categoryGroup, groups]) => ({ categoryGroup, groups }));
});

const selectedTransactionCount = computed(() => {
  return [...txStates.value.values()].filter((state) => state.selected).length;
});

const confidenceLabel: Record<SuggestionConfidence, string> = {
  high: "Hoog",
  medium: "Middel",
  low: "Laag",
};

const confidenceClass: Record<SuggestionConfidence, string> = {
  high: "text-emerald-300 bg-emerald-950/60 border-emerald-700/50",
  medium: "text-sky-300 bg-sky-950/60 border-sky-700/50",
  low: "text-amber-300 bg-amber-950/60 border-amber-700/50",
};

function resolveTargetType(categoryGroup: BudgetCategoryGroup): BudgetType {
  const matched = props.categories.find((category) => category.name === categoryGroup);
  if (categoryGroup === "Inkomsten" || matched?.type === "inkomsten") {
    return "inkomsten";
  }
  if (categoryGroup === "Spaargeld" || matched?.type === "sparen") {
    return "sparen";
  }
  return "uitgaven";
}

function budgetItemsForGroup(group: BudgetCategoryGroup): BudgetItem[] {
  return props.budgetItems.filter((item) => item.group === group);
}

function getGroupTransactionIds(group: TransactionLinkSuggestionGroup): string[] {
  return group.suggestions.map((suggestion) => suggestion.transactionId);
}

function getGroupSelectedCount(group: TransactionLinkSuggestionGroup): number {
  return getGroupTransactionIds(group).filter((id) => txStates.value.get(id)?.selected).length;
}

function isGroupFullySelected(group: TransactionLinkSuggestionGroup): boolean {
  const ids = getGroupTransactionIds(group);
  return ids.length > 0 && ids.every((id) => txStates.value.get(id)?.selected);
}

function isGroupPartiallySelected(group: TransactionLinkSuggestionGroup): boolean {
  const selected = getGroupSelectedCount(group);
  return selected > 0 && selected < getGroupTransactionIds(group).length;
}

function getEffectiveGroupAssignment(group: TransactionLinkSuggestionGroup): TxAssignmentState {
  const firstId = group.suggestions[0]?.transactionId;
  const firstState = firstId ? txStates.value.get(firstId) : undefined;
  return (
    firstState ?? {
      selected: group.defaultSelected,
      categoryGroup: group.categoryGroup,
      budgetItemId: group.budgetItemId,
      targetType: group.targetType,
      customTarget: false,
    }
  );
}

function evaluateSuggestions() {
  const ownIbans = props.bankAccounts.map((account) => account.iban).filter(Boolean);
  result.value = getTransactionLinkSuggestionService().suggest({
    transactions: props.transactions,
    rules: props.rules,
    savingsGoals: props.savingsGoals,
    budgetItems: props.budgetItems,
    categories: props.categories,
    ownIbans,
    reportingMonth: props.currentMonth,
  });

  const nextStates = new Map<string, TxAssignmentState>();
  const nextMeta = new Map<string, TransactionLinkSuggestionGroup>();

  for (const group of result.value.groups) {
    nextMeta.set(group.id, group);
    for (const suggestion of group.suggestions) {
      nextStates.set(suggestion.transactionId, {
        selected: group.defaultSelected,
        categoryGroup: suggestion.categoryGroup,
        budgetItemId: suggestion.budgetItemId,
        targetType: suggestion.targetType,
        customTarget: false,
      });
    }
  }

  txStates.value = nextStates;
  groupMeta.value = nextMeta;
  createRuleGroupIds.value = new Set(
    result.value.groups.filter((group) => group.defaultCreateRule).map((group) => group.id)
  );
  expandedGroupIds.value = new Set(result.value.groups.map((group) => group.id));
  editingTxIds.value = new Set();
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      evaluateSuggestions();
    }
  }
);

function setGroupSelection(group: TransactionLinkSuggestionGroup, selected: boolean) {
  const next = new Map(txStates.value);
  for (const suggestion of group.suggestions) {
    const current = next.get(suggestion.transactionId);
    if (!current) {
      continue;
    }
    next.set(suggestion.transactionId, { ...current, selected });
  }
  txStates.value = next;
}

function toggleGroupSelection(group: TransactionLinkSuggestionGroup) {
  setGroupSelection(group, !isGroupFullySelected(group));
}

function toggleTransactionSelection(transactionId: string) {
  const current = txStates.value.get(transactionId);
  if (!current) {
    return;
  }

  txStates.value = new Map(txStates.value).set(transactionId, {
    ...current,
    selected: !current.selected,
  });
}

function updateGroupTarget(
  group: TransactionLinkSuggestionGroup,
  categoryGroup: BudgetCategoryGroup,
  budgetItemId: string
) {
  const next = new Map(txStates.value);
  const targetType = resolveTargetType(categoryGroup);

  for (const suggestion of group.suggestions) {
    const current = next.get(suggestion.transactionId);
    if (!current || current.customTarget) {
      continue;
    }

    next.set(suggestion.transactionId, {
      ...current,
      categoryGroup,
      budgetItemId,
      targetType,
    });
  }

  txStates.value = next;
}

function handleGroupCategoryChange(
  group: TransactionLinkSuggestionGroup,
  categoryGroup: BudgetCategoryGroup
) {
  const items = budgetItemsForGroup(categoryGroup);
  const budgetItemId = items[0]?.id ?? group.budgetItemId;
  updateGroupTarget(group, categoryGroup, budgetItemId);
}

function handleGroupBudgetItemChange(group: TransactionLinkSuggestionGroup, budgetItemId: string) {
  const item = props.budgetItems.find((entry) => entry.id === budgetItemId);
  if (!item) {
    return;
  }
  updateGroupTarget(group, item.group, budgetItemId);
}

function toggleTransactionTargetEdit(transactionId: string) {
  const next = new Set(editingTxIds.value);
  if (next.has(transactionId)) {
    next.delete(transactionId);
  } else {
    next.add(transactionId);
  }
  editingTxIds.value = next;
}

function updateTransactionTarget(
  transactionId: string,
  categoryGroup: BudgetCategoryGroup,
  budgetItemId: string
) {
  const current = txStates.value.get(transactionId);
  if (!current) {
    return;
  }

  txStates.value = new Map(txStates.value).set(transactionId, {
    ...current,
    categoryGroup,
    budgetItemId,
    targetType: resolveTargetType(categoryGroup),
    customTarget: true,
  });
}

function handleTransactionCategoryChange(transactionId: string, categoryGroup: BudgetCategoryGroup) {
  const items = budgetItemsForGroup(categoryGroup);
  updateTransactionTarget(transactionId, categoryGroup, items[0]?.id ?? "");
}

function toggleCreateRule(groupId: string) {
  const next = new Set(createRuleGroupIds.value);
  if (next.has(groupId)) {
    next.delete(groupId);
  } else {
    next.add(groupId);
  }
  createRuleGroupIds.value = next;
}

function toggleExpanded(groupId: string) {
  const next = new Set(expandedGroupIds.value);
  if (next.has(groupId)) {
    next.delete(groupId);
  } else {
    next.add(groupId);
  }
  expandedGroupIds.value = next;
}

function selectAllTransactions() {
  const next = new Map(txStates.value);
  for (const [id, state] of next.entries()) {
    next.set(id, { ...state, selected: true });
  }
  txStates.value = next;
}

function clearAllTransactions() {
  const next = new Map(txStates.value);
  for (const [id, state] of next.entries()) {
    next.set(id, { ...state, selected: false });
  }
  txStates.value = next;
}

function handleSave() {
  if (!result.value) {
    return;
  }

  const payload: AutoProcessSaveAssignment[] = [];

  for (const group of result.value.groups) {
    const meta = groupMeta.value.get(group.id) ?? group;
    const createRule =
      createRuleGroupIds.value.has(group.id) && meta.createRule ? meta.createRule : undefined;

    for (const suggestion of group.suggestions) {
      const state = txStates.value.get(suggestion.transactionId);
      if (!state?.selected || !state.budgetItemId) {
        continue;
      }

      payload.push({
        transactionId: suggestion.transactionId,
        categoryGroup: state.categoryGroup,
        budgetItemId: state.budgetItemId,
        targetType: state.targetType,
        createRule: state.customTarget ? undefined : createRule,
      });
    }
  }

  if (payload.length === 0) {
    return;
  }

  props.onSave(payload);
  props.onClose();
}
</script>

<template>
  <div
    v-if="isOpen"
    id="auto-process-transactions-modal-overlay"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/75 backdrop-blur-sm animate-in fade-in"
  >
    <div
      class="bg-slate-900 border border-slate-800 w-full max-w-4xl rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[92vh]"
    >
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-2.5">
          <div class="p-2 rounded-xl bg-violet-500/20 text-violet-400 border border-violet-500/30">
            <Sparkles class="w-4 h-4" />
          </div>
          <div>
            <h3 class="font-bold text-white text-base">Transacties automatisch verwerken</h3>
            <p class="text-xs text-slate-400">
              Beoordeel per groep en per transactie; pas rubriek of post aan waar nodig
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

      <div v-if="result" class="px-5 py-3 border-b border-slate-800 bg-slate-900/80 shrink-0">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-xs">
          <div class="flex flex-wrap items-center gap-2 text-slate-300">
            <span class="font-semibold text-white">
              {{ result.groups.length }} groepen · {{ result.meta.unlinkedCount }} open
            </span>
            <span class="text-slate-500">•</span>
            <span>{{ selectedTransactionCount }} geselecteerd</span>
            <span class="text-slate-500">•</span>
            <span>{{ result.unsuggestedTransactionIds.length }} zonder suggestie</span>
          </div>
          <div class="flex items-center gap-2">
            <button
              type="button"
              class="px-2.5 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white hover:bg-slate-800"
              @click="selectAllTransactions"
            >
              Alles aan
            </button>
            <button
              type="button"
              class="px-2.5 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:text-white hover:bg-slate-800"
              @click="clearAllTransactions"
            >
              Alles uit
            </button>
          </div>
        </div>
        <p class="text-[11px] text-slate-500 mt-2">
          Beoordelaar: {{ result.meta.providerLabel }}
        </p>
      </div>

      <div class="p-5 overflow-y-auto space-y-4 text-xs">
        <div
          v-if="result && result.groups.length === 0"
          class="rounded-2xl border border-slate-800 bg-slate-800/40 p-6 text-center"
        >
          <AlertCircle class="w-8 h-8 text-slate-500 mx-auto mb-3" />
          <p class="text-white font-semibold">Geen suggesties gevonden</p>
          <p class="text-slate-400 mt-1">
            Er zijn geen open transacties die automatisch gekoppeld kunnen worden.
          </p>
        </div>

        <section
          v-for="section in groupedByCategory"
          :key="section.categoryGroup"
          class="space-y-2"
        >
          <h4 class="text-[11px] uppercase tracking-wider text-slate-500 font-bold px-1">
            {{ section.categoryGroup }}
          </h4>

          <div
            v-for="group in section.groups"
            :key="group.id"
            class="rounded-2xl border border-slate-800 bg-slate-800/40 overflow-hidden"
          >
            <div class="p-3.5 space-y-3">
              <div class="flex items-start gap-3">
                <button
                  type="button"
                  class="mt-0.5 shrink-0 text-indigo-400 hover:text-indigo-300"
                  @click="toggleGroupSelection(group)"
                >
                  <CheckSquare v-if="isGroupFullySelected(group)" class="w-4 h-4" />
                  <MinusSquare
                    v-else-if="isGroupPartiallySelected(group)"
                    class="w-4 h-4 text-violet-300"
                  />
                  <Square v-else class="w-4 h-4 text-slate-500" />
                </button>

                <div class="min-w-0 flex-1 space-y-2">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="text-slate-400">
                      {{ getGroupSelectedCount(group) }}/{{ group.suggestions.length }} geselecteerd
                    </span>
                    <span
                      class="text-[10px] px-2 py-0.5 rounded-full border font-semibold"
                      :class="confidenceClass[group.confidence]"
                    >
                      {{ confidenceLabel[group.confidence] }}
                    </span>
                  </div>
                  <p class="text-slate-400">{{ group.reason }}</p>

                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <label class="space-y-1">
                      <span class="text-[11px] text-slate-400">Rubriek</span>
                      <select
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                        :value="getEffectiveGroupAssignment(group).categoryGroup"
                        @change="
                          handleGroupCategoryChange(
                            group,
                            ($event.target as HTMLSelectElement).value as BudgetCategoryGroup
                          )
                        "
                      >
                        <option v-for="cat in categories" :key="cat.id" :value="cat.name">
                          {{ cat.name }}
                        </option>
                      </select>
                    </label>
                    <label class="space-y-1">
                      <span class="text-[11px] text-slate-400">Begrotingspost</span>
                      <select
                        class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                        :value="getEffectiveGroupAssignment(group).budgetItemId"
                        @change="
                          handleGroupBudgetItemChange(group, ($event.target as HTMLSelectElement).value)
                        "
                      >
                        <option
                          v-for="item in budgetItemsForGroup(getEffectiveGroupAssignment(group).categoryGroup)"
                          :key="item.id"
                          :value="item.id"
                        >
                          {{ item.name }}
                        </option>
                      </select>
                    </label>
                  </div>

                  <label
                    v-if="group.createRule"
                    class="inline-flex items-center gap-2 text-[11px] text-slate-300 cursor-pointer"
                  >
                    <input
                      type="checkbox"
                      class="rounded border-slate-600 bg-slate-900 text-indigo-500 focus:ring-indigo-500"
                      :checked="createRuleGroupIds.has(group.id)"
                      @change="toggleCreateRule(group.id)"
                    />
                    Maak koppelregel aan voor "{{ group.createRule.keyword }}"
                  </label>
                </div>

                <button
                  type="button"
                  class="shrink-0 text-slate-400 hover:text-white p-1 rounded-lg hover:bg-slate-700/60"
                  @click="toggleExpanded(group.id)"
                >
                  <ChevronDown v-if="expandedGroupIds.has(group.id)" class="w-4 h-4" />
                  <ChevronRight v-else class="w-4 h-4" />
                </button>
              </div>
            </div>

            <div
              v-if="expandedGroupIds.has(group.id)"
              class="border-t border-slate-800/80 divide-y divide-slate-800/80"
            >
              <div
                v-for="suggestion in group.suggestions"
                :key="suggestion.transactionId"
                class="px-4 py-3 bg-slate-900/40 space-y-2"
              >
                <div class="flex items-start gap-3">
                  <button
                    type="button"
                    class="mt-0.5 shrink-0 text-indigo-400 hover:text-indigo-300"
                    @click="toggleTransactionSelection(suggestion.transactionId)"
                  >
                    <CheckSquare
                      v-if="txStates.get(suggestion.transactionId)?.selected"
                      class="w-4 h-4"
                    />
                    <Square v-else class="w-4 h-4 text-slate-500" />
                  </button>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <TransactionDate
                        :date="transactionMap.get(suggestion.transactionId)?.date ?? ''"
                        :time="transactionMap.get(suggestion.transactionId)?.time"
                        size="sm"
                      />
                      <span
                        v-if="txStates.get(suggestion.transactionId)?.customTarget"
                        class="text-[10px] px-1.5 py-0.5 rounded bg-violet-950/70 text-violet-300 border border-violet-800/50"
                      >
                        Aangepast
                      </span>
                    </div>
                    <p class="text-white font-medium mt-1 break-words">
                      {{ transactionMap.get(suggestion.transactionId)?.description }}
                    </p>
                    <p
                      v-if="transactionMap.get(suggestion.transactionId)?.counterparty"
                      class="text-slate-500 mt-0.5"
                    >
                      {{ transactionMap.get(suggestion.transactionId)?.counterparty }}
                    </p>
                  </div>

                  <div class="flex flex-col items-end gap-2 shrink-0">
                    <div
                      class="font-mono font-bold"
                      :class="
                        (transactionMap.get(suggestion.transactionId)?.amount ?? 0) >= 0
                          ? 'text-emerald-400'
                          : 'text-rose-400'
                      "
                    >
                      €
                      {{
                        Math.abs(
                          transactionMap.get(suggestion.transactionId)?.amount ?? 0
                        ).toLocaleString("nl-NL", { minimumFractionDigits: 2 })
                      }}
                    </div>
                    <button
                      type="button"
                      class="text-[11px] text-indigo-300 hover:text-white"
                      @click="toggleTransactionTargetEdit(suggestion.transactionId)"
                    >
                      {{ editingTxIds.has(suggestion.transactionId) ? "Sluit" : "Aanpassen" }}
                    </button>
                  </div>
                </div>

                <div
                  v-if="editingTxIds.has(suggestion.transactionId)"
                  class="grid grid-cols-1 sm:grid-cols-2 gap-2 pl-7"
                >
                  <label class="space-y-1">
                    <span class="text-[11px] text-slate-400">Rubriek</span>
                    <select
                      class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                      :value="txStates.get(suggestion.transactionId)?.categoryGroup"
                      @change="
                        handleTransactionCategoryChange(
                          suggestion.transactionId,
                          ($event.target as HTMLSelectElement).value as BudgetCategoryGroup
                        )
                      "
                    >
                      <option v-for="cat in categories" :key="cat.id" :value="cat.name">
                        {{ cat.name }}
                      </option>
                    </select>
                  </label>
                  <label class="space-y-1">
                    <span class="text-[11px] text-slate-400">Begrotingspost</span>
                    <select
                      class="w-full bg-slate-900 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                      :value="txStates.get(suggestion.transactionId)?.budgetItemId"
                      @change="
                        updateTransactionTarget(
                          suggestion.transactionId,
                          txStates.get(suggestion.transactionId)?.categoryGroup ?? group.categoryGroup,
                          ($event.target as HTMLSelectElement).value
                        )
                      "
                    >
                      <option
                        v-for="item in budgetItemsForGroup(
                          txStates.get(suggestion.transactionId)?.categoryGroup ?? group.categoryGroup
                        )"
                        :key="item.id"
                        :value="item.id"
                      >
                        {{ item.name }}
                      </option>
                    </select>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </section>

        <div
          v-if="result && result.unsuggestedTransactionIds.length > 0"
          class="rounded-2xl border border-amber-700/40 bg-amber-950/20 p-4"
        >
          <div class="flex items-start gap-2">
            <AlertCircle class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
            <div>
              <p class="text-amber-200 font-semibold">
                {{ result.unsuggestedTransactionIds.length }}
                {{
                  result.unsuggestedTransactionIds.length === 1
                    ? "transactie"
                    : "transacties"
                }}
                zonder suggestie
              </p>
              <p class="text-amber-200/70 mt-1">
                Deze blijven open staan tot je ze handmatig koppelt.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="px-5 py-4 border-t border-slate-800 bg-slate-850 flex items-center justify-between gap-3 shrink-0">
        <button
          type="button"
          class="px-4 py-2 rounded-xl border border-slate-700 text-slate-300 hover:text-white hover:bg-slate-800 transition-colors text-xs font-semibold"
          @click="onClose"
        >
          Annuleren
        </button>
        <button
          type="button"
          class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-500 disabled:opacity-40 disabled:cursor-not-allowed text-white transition-colors text-xs font-semibold flex items-center gap-2"
          :disabled="selectedTransactionCount === 0"
          @click="handleSave"
        >
          <CheckCircle2 class="w-4 h-4" />
          {{ selectedTransactionCount }} transacties koppelen
        </button>
      </div>
    </div>
  </div>
</template>
