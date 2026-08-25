<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { X, Sliders } from "lucide-vue-next";
import type {
  Rule,
  BudgetCategoryGroup,
  BudgetType,
  BudgetItem,
  CategoryDefinition,
  Transaction,
} from "../types";
import { matchingUnlinkedTransactions } from "../matchRule";

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    onClose: () => void;
    onAdd: (rule: Omit<Rule, "id" | "matchedCount">) => void;
    initialKeyword?: string;
    initialGroup?: BudgetCategoryGroup;
    initialBudgetItemId?: string;
    budgetItems?: BudgetItem[];
    categories?: CategoryDefinition[];
    transactions?: Transaction[];
  }>(),
  {
    initialKeyword: "",
    initialGroup: "Dagelijks Leven",
    initialBudgetItemId: "",
    budgetItems: () => [],
    categories: () => [],
    transactions: () => [],
  }
);

const name = ref("");
const keyword = ref("");
const targetGroup = ref<BudgetCategoryGroup>("Dagelijks Leven");
const targetBudgetItemId = ref("");
const targetType = ref<BudgetType>("uitgaven");
const matchField = ref<"description" | "counterparty" | "both">("description");

watch(
  () =>
    [
      props.isOpen,
      props.initialKeyword,
      props.initialGroup,
      props.initialBudgetItemId,
      props.categories,
    ] as const,
  () => {
    if (!props.isOpen) return;
    name.value = props.initialKeyword ? `Regel: ${props.initialKeyword}` : "";
    keyword.value = props.initialKeyword;
    targetGroup.value = props.initialGroup;
    targetBudgetItemId.value = props.initialBudgetItemId;

    const catMatch = props.categories.find((c) => c.name === props.initialGroup);
    if (catMatch) {
      targetType.value = catMatch.type;
    } else if (props.initialGroup === "Inkomsten") {
      targetType.value = "inkomsten";
    } else if (props.initialGroup === "Spaargeld") {
      targetType.value = "sparen";
    } else {
      targetType.value = "uitgaven";
    }
  }
);

const extraMatches = computed(() =>
  matchingUnlinkedTransactions(props.transactions, keyword.value, matchField.value, undefined, targetType.value)
);

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

  const itemsInGrp = props.budgetItems.filter((i) => i.group === grp);
  targetBudgetItemId.value = itemsInGrp.length > 0 ? itemsInGrp[0].id : "";
}

function handleSubmit() {
  if (!name.value.trim() || !keyword.value.trim()) return;

  props.onAdd({
    name: name.value.trim(),
    keyword: keyword.value.trim(),
    matchField: matchField.value,
    targetGroup: targetGroup.value,
    targetBudgetItemId: targetBudgetItemId.value || undefined,
    targetType: targetType.value,
    isActive: true,
  });

  props.onClose();
}
</script>

<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm animate-in fade-in"
  >
    <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
      <div class="bg-slate-850 px-5 py-4 border-b border-slate-800 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Sliders class="w-4 h-4 text-indigo-400" />
          <h3 class="font-bold text-white text-base">Nieuwe Koppelregel Aanmaken</h3>
        </div>
        <button type="button" class="text-slate-400 hover:text-white p-1 rounded-lg" @click="onClose">
          <X class="w-4 h-4" />
        </button>
      </div>

      <form class="p-5 space-y-4 text-xs" @submit.prevent="handleSubmit">
        <div>
          <label class="block text-slate-300 font-semibold mb-1">Naam van de Regel</label>
          <input
            v-model="name"
            type="text"
            required
            placeholder="Bijv. Jumbo Supermarkt of Spotify"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white placeholder-slate-400 focus:outline-none focus:border-indigo-500"
          />
        </div>

        <div>
          <label class="block text-slate-300 font-semibold mb-1">Trefwoord om op te filteren</label>
          <input
            v-model="keyword"
            type="text"
            required
            placeholder="Bijv. JUMBO, Spotify, Dirk, Kruidvat..."
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-mono placeholder-slate-400 focus:outline-none focus:border-indigo-500"
          />
          <p v-if="keyword.trim().length < 2" class="text-[10px] text-slate-400 mt-1">
            Typ minstens 2 tekens om te zien welke ongekoppelde rijen meegaan.
          </p>
          <div v-else class="mt-2 bg-indigo-950/40 border border-indigo-800/50 rounded-lg p-2.5 space-y-1.5">
            <p class="text-[11px] text-indigo-200 font-semibold">
              {{
                extraMatches.length === 0
                  ? `Geen ongekoppelde rijen met dit trefwoord (${
                      targetType === "inkomsten"
                        ? "alleen erbij"
                        : targetType === "uitgaven"
                          ? "alleen eraf"
                          : "erbij en eraf"
                    }).`
                  : `${extraMatches.length} ongekoppelde ${extraMatches.length === 1 ? "rij wordt" : "rijen worden"} nu ook gekoppeld.`
              }}
            </p>
            <ul v-if="extraMatches.length > 0" class="space-y-1">
              <li
                v-for="tx in extraMatches.slice(0, 4)"
                :key="tx.id"
                class="text-[10px] text-slate-400 truncate"
              >
                {{ tx.date }} · {{ tx.amount > 0 ? "+" : "−" }}€
                {{ Math.abs(tx.amount).toLocaleString("nl-NL", { minimumFractionDigits: 2 }) }}
                · {{ tx.description }}
              </li>
              <li v-if="extraMatches.length > 4" class="text-[10px] text-slate-500">
                + {{ extraMatches.length - 4 }} meer
              </li>
            </ul>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-slate-300 font-semibold mb-1">Doel Categorie (Rubriek)</label>
            <select
              :value="targetGroup"
              class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
              @change="handleGroupChange(($event.target as HTMLSelectElement).value)"
            >
              <option v-for="g in availableGroups" :key="g" :value="g">{{ g }}</option>
            </select>
          </div>

          <div>
            <label class="block text-slate-300 font-semibold mb-1">Specifieke Begrotingspost</label>
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

        <div>
          <label class="block text-slate-300 font-semibold mb-1">Zoekveld</label>
          <select
            v-model="matchField"
            class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
          >
            <option value="description">Omschrijving van transactie</option>
            <option value="counterparty">Naam van tegenpartij</option>
            <option value="both">Zowel omschrijving als tegenpartij</option>
          </select>
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
            {{
              extraMatches.length > 0
                ? `Opslaan & ${extraMatches.length} rijen koppelen`
                : "Koppelregel Opslaan"
            }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
